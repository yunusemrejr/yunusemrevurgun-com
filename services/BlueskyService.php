<?php
/**
 * BlueskyService — server-side Bluesky (AT Protocol) cross-posting for updates.
 *
 * Single-account integration. Reads configuration EXCLUSIVELY from server-side
 * environment variables:
 *   BLUESKY_HANDLE (no leading @), BLUESKY_APP_PASSWORD, BLUESKY_SERVICE_URL
 *
 * Authenticates server-side with com.atproto.server.createSession, then creates
 * app.bsky.feed.post records via com.atproto.repo.createRecord.
 *
 * The App Password, access JWT, and refresh JWT are NEVER exposed to client-side
 * JavaScript, API responses, HTML, logs, or Git. A fresh session is created per
 * publish; on an auth failure (HTTP 401) the session is refreshed/recreated once
 * and the request retried. Idempotency: each update uses a deterministic record
 * key (rkey 'update-{id}'), so retries re-target the same record — a duplicate
 * post is impossible (if the record already exists it is recovered, not recreated).
 */
require_once dirname(__DIR__) . '/config/setPath.php';

/** Authentication failure (bad app password / expired session) — permanent, never retried. */
class BlueskyAuthException extends RuntimeException {}

class BlueskyService {

    const SYNC_NOT_REQUESTED = 'not_requested';
    const SYNC_PENDING = 'pending';
    const SYNC_PUBLISHED = 'published';
    const SYNC_FAILED = 'failed';

    const COLLECTION_POST = 'app.bsky.feed.post';
    const POST_CHAR_LIMIT = 300;

    /** Test seam: callable(string $url, array $headers, string $body, string $method) -> array{int,string}. Tests only. */
    public static $httpOverride = null;

    private $handle;
    private $appPassword;
    private $serviceUrl;

    public function __construct() {
        $this->handle = trim((string) (getenv('BLUESKY_HANDLE') ?: ''));
        $this->handle = ltrim($this->handle, '@'); // never allow a leading @
        $this->appPassword = (string) (getenv('BLUESKY_APP_PASSWORD') ?: '');
        $this->serviceUrl = rtrim((string) (getenv('BLUESKY_SERVICE_URL') ?: 'https://bsky.social'), '/');
    }

    public function isConfigured(): bool {
        return $this->handle !== '' && $this->appPassword !== '' && $this->serviceUrl !== '';
    }

    public function getHandle(): string { return $this->handle; }
    public function getServiceUrl(): string { return $this->serviceUrl; }

    /**
     * Deterministic record key per update — retries re-target the same record
     * (no duplicates). app.bsky.feed.post enforces TID-format rkeys
     * (13 chars, base32 alphabet 234567abcdefghijklmnopqrstuvwxyz, first char
     * 2-7), so the key is derived from a hash of the update id instead of a
     * human-readable slug.
     */
    public static function rkeyForUpdate($updateId): string {
        return self::makeTid('yunusemrevurgun.com/updates/' . (int) $updateId);
    }

    /** Build a TID-format record key deterministically from a seed string. */
    private static function makeTid(string $seed): string {
        $alphabet = '234567abcdefghijklmnopqrstuvwxyz';
        $hash = hash('sha256', $seed, true);
        $rkey = '';
        for ($i = 0; $i < 13; $i++) {
            $rkey .= $alphabet[ord($hash[$i]) % 32];
        }
        // First char must be 2-7 (TID timestamp field).
        $rkey = '234567'[ord($hash[0]) % 6] . substr($rkey, 1);
        return $rkey;
    }

    /**
     * Publish a website update to Bluesky.
     *
     * @return array{success:bool, status_uri:?string, status_url:?string, http_status:int, retryable:bool, error:?string}
     */
    public function publish(string $title, string $markdown, string $canonicalUrl, string $rkey): array {
        if (!$this->isConfigured()) {
            return $this->result(false, null, null, 0, false, 'Bluesky is not configured (BLUESKY_HANDLE / BLUESKY_APP_PASSWORD missing)');
        }
        $built = $this->buildStatusText($title, $markdown, $canonicalUrl);
        if ($built === null) {
            return $this->result(false, null, null, 0, false, 'Generated Bluesky post is empty; nothing was posted.');
        }

        try {
            $session = $this->createSession();
        } catch (BlueskyAuthException $e) {
            return $this->result(false, null, null, 0, false, 'Bluesky authentication failed: ' . $this->sanitizeError($e->getMessage()));
        } catch (Throwable $e) {
            return $this->result(false, null, null, 0, true, 'Network error: ' . $this->sanitizeError($e->getMessage()));
        }

        $record = [
            'text' => $built['text'],
            'createdAt' => gmdate('c'),
            'langs' => ['en'],
        ];
        if (!empty($built['facets'])) {
            $record['facets'] = $built['facets'];
        }

        // createRecord with the deterministic rkey; on auth failure recreate the
        // session once; on already-exists, recover the existing record.
        try {
            [$status, $body] = $this->createRecord($session['accessJwt'], $session['did'], $rkey, $record);
            if ($status === 401) {
                $session = $this->createSession(); // recreate session (access JWT refresh equivalent)
                [$status, $body] = $this->createRecord($session['accessJwt'], $session['did'], $rkey, $record);
            }
        } catch (Throwable $e) {
            return $this->result(false, null, null, 0, true, 'Network error: ' . $this->sanitizeError($e->getMessage()));
        }

        if ($status >= 200 && $status < 300) {
            $json = json_decode($body, true);
            if (!is_array($json) || !isset($json['uri'])) {
                return $this->result(false, null, null, $status, true, 'Malformed Bluesky response (HTTP ' . $status . ').');
            }
            return $this->result(true, (string) $json['uri'], $this->statusUrl((string) $json['uri']), $status, false, null);
        }

        // A record that already exists at this rkey means a previous attempt
        // succeeded but its response was lost — recover it instead of duplicating.
        // (Real PDSes return 400/409 or even 500 for an existing rkey.)
        if (in_array($status, [400, 409, 500, 502, 503, 504], true)) {
            try {
                $existing = $this->getRecord($session['accessJwt'], $session['did'], $rkey);
                if ($existing !== null) {
                    return $this->result(true, $existing['uri'], $this->statusUrl($existing['uri']), 200, false, null);
                }
            } catch (Throwable $e) {
                // fall through to the normal error path below
            }
        }

        $retryable = $this->isRetryable($status);
        $detail = $this->extractErrorDetail($body);
        return $this->result(false, null, null, $status, $retryable, 'Bluesky error HTTP ' . $status . ($detail !== '' ? ': ' . $detail : ''));
    }

    /** Send a test post WITHOUT creating a website update. Same shape as publish(). */
    public function testPost(): array {
        if (!$this->isConfigured()) {
            return $this->result(false, null, null, 0, false, 'Bluesky is not configured (BLUESKY_HANDLE / BLUESKY_APP_PASSWORD missing)');
        }
        $siteBase = (getenv('MODE') === 'production') ? FULL_BASE_PATH : 'https://yunusemrevurgun.com/';
        $text = "Test post from the yunusemrevurgun.com admin — " . gmdate('c') . "\n\nRead more:\n" . rtrim($siteBase, '/') . '/';
        $rkey = self::makeTid('test:' . bin2hex(random_bytes(8)));
        $facets = $this->buildFacets($text, rtrim($siteBase, '/') . '/');

        try {
            $session = $this->createSession();
        } catch (BlueskyAuthException $e) {
            return $this->result(false, null, null, 0, false, 'Bluesky authentication failed: ' . $this->sanitizeError($e->getMessage()));
        } catch (Throwable $e) {
            return $this->result(false, null, null, 0, true, 'Network error: ' . $this->sanitizeError($e->getMessage()));
        }
        $record = ['text' => $text, 'createdAt' => gmdate('c'), 'langs' => ['en']];
        if (!empty($facets)) $record['facets'] = $facets;

        try {
            [$status, $body] = $this->createRecord($session['accessJwt'], $session['did'], $rkey, $record);
            if ($status === 401) {
                $session = $this->createSession();
                [$status, $body] = $this->createRecord($session['accessJwt'], $session['did'], $rkey, $record);
            }
        } catch (Throwable $e) {
            return $this->result(false, null, null, 0, true, 'Network error: ' . $this->sanitizeError($e->getMessage()));
        }
        if ($status >= 200 && $status < 300) {
            $json = json_decode($body, true);
            if (!is_array($json) || !isset($json['uri'])) {
                return $this->result(false, null, null, $status, true, 'Malformed Bluesky response (HTTP ' . $status . ').');
            }
            return $this->result(true, (string) $json['uri'], $this->statusUrl((string) $json['uri']), $status, false, null);
        }
        $retryable = $this->isRetryable($status);
        $detail = $this->extractErrorDetail($body);
        return $this->result(false, null, null, $status, $retryable, 'Bluesky error HTTP ' . $status . ($detail !== '' ? ': ' . $detail : ''));
    }

    /** Transient failures only: network(0), 429, 500, 502, 503, 504. */
    public function isRetryable(int $httpStatus): bool {
        return in_array($httpStatus, [0, 429, 500, 502, 503, 504], true);
    }

    /**
     * Build the Bluesky post text (≤300 chars) plus link facets (byte offsets).
     * Format: <title>\n\n<short text>\n\nRead more:\n<url>. The canonical URL
     * is never duplicated, truncation is word-safe, empty posts are refused.
     * @return array{text:string, facets:array}|null
     */
    public function buildStatusText(string $title, string $markdown, string $canonicalUrl): ?array {
        $limit = self::POST_CHAR_LIMIT;
        $title = $this->cleanText($title, 300);
        $text = $this->markdownToPlainText($markdown);

        if ($title === '' && $text !== '') {
            $first = preg_split('/[.!?…](?:\s|$)/u', $text, 2);
            $candidate = trim((string) ($first[0] ?? ''));
            if ($candidate !== '') {
                $title = $candidate;
                $text = trim((string) ($first[1] ?? ''));
            }
        }

        // A post with neither title nor body is empty — never publish link-only spam.
        if ($title === '' && $text === '') {
            return null;
        }

        $hasUrl = $canonicalUrl !== '' && mb_strpos($text, $canonicalUrl) !== false;
        $linkBlock = ($canonicalUrl !== '' && !$hasUrl) ? "Read more:\n" . $canonicalUrl : '';

        $sep = ($title !== '' && $text !== '') ? "\n\n" : '';
        $main = $title . $sep . $text;
        $linkSep = ($main !== '' && $linkBlock !== '') ? "\n\n" : '';
        $full = $main . $linkSep . $linkBlock;

        if (mb_strlen($full) > $limit) {
            $reserved = ($linkBlock !== '') ? mb_strlen($linkBlock) + ($main !== '' ? 2 : 0) : 0;
            $mainBudget = max(20, $limit - $reserved);
            $main = $this->truncateWordSafe($main, $mainBudget);
            $full = trim($main . (($main !== '' && $linkBlock !== '') ? "\n\n" : '') . $linkBlock);
        }
        $full = trim($full);
        if ($full === '') return null;

        return [
            'text' => $full,
            'facets' => $this->buildFacets($full, $canonicalUrl),
        ];
    }

    /** Link facet (byte offsets) for the canonical URL wherever it appears in the text. */
    private function buildFacets(string $text, string $url): array {
        if ($url === '') return [];
        $pos = strpos($text, $url);
        if ($pos === false) return [];
        return [[
            'index' => ['byteStart' => $pos, 'byteEnd' => $pos + strlen($url)],
            'features' => [[
                '$type' => 'app.bsky.richtext.facet#link',
                'uri' => $url,
            ]],
        ]];
    }

    /** Human-readable Bluesky post URL from an at:// uri. */
    private function statusUrl(string $uri): string {
        $parts = explode('/', $uri);
        $rkey = end($parts);
        return 'https://bsky.app/profile/' . rawurlencode($this->handle) . '/post/' . rawurlencode((string) $rkey);
    }

    // ------------------------------------------------------------------
    // AT Protocol calls
    // ------------------------------------------------------------------

    /**
     * com.atproto.server.createSession
     * @return array{accessJwt:string, refreshJwt:string, did:string}
     */
    private function createSession(): array {
        $url = $this->serviceUrl . '/xrpc/com.atproto.server.createSession';
        $payload = json_encode([
            'identifier' => $this->handle,
            'password' => $this->appPassword,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json'];

        [$status, $body] = $this->httpPost($url, $headers, $payload, 30);
        $json = json_decode($body, true);
        if ($status >= 200 && $status < 300 && is_array($json) && isset($json['accessJwt'], $json['refreshJwt'], $json['did'])) {
            return [
                'accessJwt' => (string) $json['accessJwt'],
                'refreshJwt' => (string) $json['refreshJwt'],
                'did' => (string) $json['did'],
            ];
        }
        $detail = $this->extractErrorDetail($body);
        throw new BlueskyAuthException('HTTP ' . $status . ($detail !== '' ? ': ' . $detail : ''));
    }

    /**
     * com.atproto.repo.createRecord
     * @return array{int,string} [httpStatus, body]
     */
    private function createRecord(string $accessJwt, string $did, string $rkey, array $record): array {
        $url = $this->serviceUrl . '/xrpc/com.atproto.repo.createRecord';
        $payload = json_encode([
            'repo' => $did,
            'collection' => self::COLLECTION_POST,
            'rkey' => $rkey,
            'record' => $record,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Authorization: Bearer ' . $accessJwt,
            'Content-Type: application/json',
        ];
        return $this->httpPost($url, $headers, $payload, 30);
    }

    /**
     * com.atproto.repo.getRecord — recover an existing record at an rkey.
     * @return array{uri:string, cid:string}|null
     */
    private function getRecord(string $accessJwt, string $did, string $rkey): ?array {
        $url = $this->serviceUrl . '/xrpc/com.atproto.repo.getRecord?repo=' . rawurlencode($did)
            . '&collection=' . rawurlencode(self::COLLECTION_POST) . '&rkey=' . rawurlencode($rkey);
        [$status, $body] = $this->httpGet($url, ['Authorization: Bearer ' . $accessJwt], 20);
        if ($status >= 200 && $status < 300) {
            $json = json_decode($body, true);
            if (is_array($json) && isset($json['uri'])) {
                return ['uri' => (string) $json['uri'], 'cid' => (string) ($json['cid'] ?? '')];
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Text helpers (mirror MastodonService; kept local for isolation)
    // ------------------------------------------------------------------

    private function cleanText(string $text, int $maxLen): string {
        $text = strip_tags($text);
        $text = preg_replace('/[\t\r\f\v]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ ]{2,}/u', ' ', $text) ?? $text;
        $text = trim($text);
        if (mb_strlen($text) > $maxLen) {
            $text = $this->truncateWordSafe($text, $maxLen);
        }
        return $text;
    }

    private function markdownToPlainText(string $md): string {
        $md = (string) preg_replace('/```[a-z]*\n?/i', '', $md);
        $md = (string) preg_replace('/`([^`]+)`/u', '$1', $md);
        $md = (string) preg_replace('/!\[([^\]]*)\]\([^)]*\)/u', '$1', $md);
        $md = (string) preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $md);
        $md = (string) preg_replace('/^#{1,6}\s+/mu', '', $md);
        $md = (string) preg_replace('/^>\s?/mu', '', $md);
        $md = (string) preg_replace('/^\s*[-*+]\s+/mu', '• ', $md);
        $md = (string) preg_replace('/\*\*([^*]+)\*\*/u', '$1', $md);
        $md = (string) preg_replace('/__([^_]+)__/u', '$1', $md);
        $md = (string) preg_replace('/\*([^*]+)\*/u', '$1', $md);
        $md = (string) preg_replace('/_([^_]+)_/u', '$1', $md);
        $md = (string) preg_replace('/~~([^~]+)~~/u', '$1', $md);
        $md = strip_tags($md);
        $md = (string) preg_replace('/\n{3,}/u', "\n\n", $md);
        $md = (string) preg_replace('/[ \t]+\n/u', "\n", $md);
        return trim($md);
    }

    private function truncateWordSafe(string $text, int $maxLen): string {
        if (mb_strlen($text) <= $maxLen) return $text;
        $room = max(1, $maxLen - 1);
        $cut = mb_substr($text, 0, $room);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }
        $cut = rtrim($cut);
        if ($cut === '') {
            $cut = mb_substr($text, 0, $maxLen - 1);
        }
        return $cut . '…';
    }

    private function extractErrorDetail(string $body): string {
        $json = json_decode($body, true);
        if (is_array($json) && isset($json['message']) && is_string($json['message'])) {
            return $this->sanitizeError(mb_substr($json['message'], 0, 200));
        }
        if (is_array($json) && isset($json['error']) && is_string($json['error'])) {
            return $this->sanitizeError(mb_substr($json['error'], 0, 200));
        }
        return $this->sanitizeError(mb_substr(trim((string) $body), 0, 200));
    }

    /** Never record the App Password or any JWT in stored/logged errors. */
    private function sanitizeError(string $message): string {
        if ($this->appPassword !== '') {
            $message = str_replace($this->appPassword, '[REDACTED]', $message);
        }
        $message = (string) preg_replace('/accessJwt["\']?\s*[:=]\s*["\']?[A-Za-z0-9._-]+/i', 'accessJwt=[REDACTED]', $message);
        $message = (string) preg_replace('/refreshJwt["\']?\s*[:=]\s*["\']?[A-Za-z0-9._-]+/i', 'refreshJwt=[REDACTED]', $message);
        $message = (string) preg_replace('/Authorization:\s*Bearer\s+[^\s,]+/i', 'Authorization: [REDACTED]', $message);
        return mb_substr($message, 0, 300);
    }

    private function result(bool $success, $statusUri, $statusUrl, int $httpStatus, bool $retryable, ?string $error): array {
        return [
            'success' => $success,
            'status_uri' => $statusUri,
            'status_url' => $statusUrl,
            'http_status' => $httpStatus,
            'retryable' => $retryable,
            'error' => $error,
        ];
    }

    // ------------------------------------------------------------------
    // HTTP layer (cURL by default; injectable for tests)
    // ------------------------------------------------------------------

    protected function httpPost(string $url, array $headers, string $body, int $timeoutSec): array {
        if (is_callable(self::$httpOverride)) {
            return (self::$httpOverride)($url, $headers, $body, 'POST');
        }
        return $this->curlRequest('POST', $url, $headers, $body, $timeoutSec);
    }

    protected function httpGet(string $url, array $headers, int $timeoutSec): array {
        if (is_callable(self::$httpOverride)) {
            return (self::$httpOverride)($url, $headers, '', 'GET');
        }
        return $this->curlRequest('GET', $url, $headers, '', $timeoutSec);
    }

    private function curlRequest(string $method, string $url, array $headers, string $body, int $timeoutSec): array {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        if (!empty($headers)) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new RuntimeException('cURL error: ' . $err);
        }
        return [$httpStatus, (string) $resp];
    }
}
