<?php
/**
 * MastodonService — server-side Mastodon cross-posting for website updates.
 *
 * Single-account integration (no multi-user OAuth). Reads configuration
 * EXCLUSIVELY from server-side environment variables:
 *   MASTODON_BASE_URL, MASTODON_ACCESS_TOKEN,
 *   MASTODON_DEFAULT_VISIBILITY (default public),
 *   MASTODON_DEFAULT_LANGUAGE (default en)
 *
 * The access token is never exposed to client-side JavaScript, API responses,
 * HTML, logs, or Git. Publish via POST {base}/api/v1/statuses with an
 * Idempotency-Key (stable per local update) so retries never duplicate posts.
 *
 * The token has only the write:statuses OAuth scope — this service never calls
 * edit/delete endpoints.
 */
require_once dirname(__DIR__) . '/config/setPath.php';

class MastodonService {

    const SYNC_NOT_REQUESTED = 'not_requested';
    const SYNC_PENDING = 'pending';
    const SYNC_PUBLISHED = 'published';
    const SYNC_FAILED = 'failed';

    const VIS_PUBLIC = 'public';
    const VIS_UNLISTED = 'unlisted';
    const VIS_PRIVATE = 'private';

    /** Test seam: callable(string $url, array $headers, string $body, string $method) -> array{int,string}. Set by tests only; never in production code paths. */
    public static $httpOverride = null;

    private $baseUrl;
    private $accessToken;
    private $visibility;
    private $language;
    private $charLimit = null;

    public function __construct() {
        $this->baseUrl = rtrim((string) (getenv('MASTODON_BASE_URL') ?: ''), '/');
        $this->accessToken = (string) (getenv('MASTODON_ACCESS_TOKEN') ?: '');
        $vis = (string) (getenv('MASTODON_DEFAULT_VISIBILITY') ?: '');
        $this->visibility = in_array($vis, [self::VIS_PUBLIC, self::VIS_UNLISTED, self::VIS_PRIVATE], true) ? $vis : self::VIS_PUBLIC;
        $this->language = (string) (getenv('MASTODON_DEFAULT_LANGUAGE') ?: 'en');
        if ($this->language === '') $this->language = 'en';
    }

    public function isConfigured(): bool {
        return $this->baseUrl !== '' && $this->accessToken !== '';
    }

    public function getVisibility(): string { return $this->visibility; }
    public function getLanguage(): string { return $this->language; }
    public function getBaseUrl(): string { return $this->baseUrl; }

    /** Stable, unique idempotency key derived from the local update id (reused on retries). */
    public static function idempotencyKeyForUpdate($updateId): string {
        return hash('sha256', 'yunusemrevurgun.com/updates/' . (int) $updateId);
    }

    /**
     * Publish a website update to Mastodon.
     *
     * @return array{success:bool, status_id:?string, status_url:?string, http_status:int, retryable:bool, error:?string}
     */
    public function publish(string $title, string $markdown, string $canonicalUrl, string $idempotencyKey, ?string $visibility = null, ?string $language = null): array {
        if (!$this->isConfigured()) {
            return $this->result(false, null, null, 0, false, 'Mastodon is not configured (MASTODON_BASE_URL / MASTODON_ACCESS_TOKEN missing)');
        }

        $status = $this->buildStatusText($title, $markdown, $canonicalUrl);
        if ($status === null || trim($status) === '') {
            return $this->result(false, null, null, 0, false, 'Generated Mastodon status is empty; nothing was posted.');
        }

        $url = $this->baseUrl . '/api/v1/statuses';
        $payload = json_encode([
            'status' => $status,
            'visibility' => $this->normalizeVisibility($visibility),
            'language' => ($language !== null && $language !== '') ? $language : $this->language,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return $this->result(false, null, null, 0, false, 'Failed to encode Mastodon payload.');
        }

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Idempotency-Key: ' . $idempotencyKey,
        ];

        try {
            [$httpStatus, $body] = $this->httpPost($url, $headers, $payload, 30);
        } catch (Throwable $e) {
            // Connection failures / timeouts are transient.
            return $this->result(false, null, null, 0, true, 'Network error: ' . $this->sanitizeError($e->getMessage()));
        }

        if ($httpStatus >= 200 && $httpStatus < 300) {
            $json = json_decode($body, true);
            if (!is_array($json) || !isset($json['id'])) {
                return $this->result(false, null, null, $httpStatus, true, 'Malformed Mastodon response (HTTP ' . $httpStatus . ').');
            }
            $statusId = (string) $json['id'];
            $statusUrl = $this->extractStatusUrl($json);
            return $this->result(true, $statusId, $statusUrl, $httpStatus, false, null);
        }

        $retryable = $this->isRetryable($httpStatus);
        $detail = $this->extractErrorDetail($body);
        $error = 'Mastodon error HTTP ' . $httpStatus . ($detail !== '' ? ': ' . $detail : '');
        return $this->result(false, null, null, $httpStatus, $retryable, $error);
    }

    /**
     * Send a test post WITHOUT creating a website update (protected admin action).
     * @return array same shape as publish()
     */
    public function testPost(): array {
        if (!$this->isConfigured()) {
            return $this->result(false, null, null, 0, false, 'Mastodon is not configured (MASTODON_BASE_URL / MASTODON_ACCESS_TOKEN missing)');
        }
        $siteBase = (getenv('MODE') === 'production') ? FULL_BASE_PATH : 'https://yunusemrevurgun.com/';
        $status = "Test post from the yunusemrevurgun.com admin — " . gmdate('c') . "

Read more:
" . rtrim($siteBase, '/') . '/';
        // Fresh key per test post (never reused for a real update).
        $key = hash('sha256', 'test-post:' . bin2hex(random_bytes(8)) . ':' . gmdate('U'));

        $url = $this->baseUrl . '/api/v1/statuses';
        $payload = json_encode([
            'status' => $status,
            'visibility' => $this->visibility,
            'language' => $this->language,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Idempotency-Key: ' . $key,
        ];
        try {
            [$httpStatus, $body] = $this->httpPost($url, $headers, $payload, 30);
        } catch (Throwable $e) {
            return $this->result(false, null, null, 0, true, 'Network error: ' . $this->sanitizeError($e->getMessage()));
        }
        if ($httpStatus >= 200 && $httpStatus < 300) {
            $json = json_decode($body, true);
            if (!is_array($json) || !isset($json['id'])) {
                return $this->result(false, null, null, $httpStatus, true, 'Malformed Mastodon response (HTTP ' . $httpStatus . ').');
            }
            return $this->result(true, (string) $json['id'], $this->extractStatusUrl($json), $httpStatus, false, null);
        }
        $retryable = $this->isRetryable($httpStatus);
        $detail = $this->extractErrorDetail($body);
        return $this->result(false, null, null, $httpStatus, $retryable, 'Mastodon error HTTP ' . $httpStatus . ($detail !== '' ? ': ' . $detail : ''));
    }

    /** Transient failures only: network(0), 429, 500, 502, 503, 504. */
    public function isRetryable(int $httpStatus): bool {
        return in_array($httpStatus, [0, 429, 500, 502, 503, 504], true);
    }

    /**
     * Build the Mastodon status text from a website update.
     * Format: <title>

<short text>

Read more:
<url>
     * The "Read more:" block is omitted when the canonical URL already appears
     * in the text. Truncation is word-safe and reserves space for the link.
     * Returns null when the resulting status would be empty.
     */
    public function buildStatusText(string $title, string $markdown, string $canonicalUrl): ?string {
        $limit = $this->getCharacterLimit();
        $title = $this->cleanText($title, 300);
        $text = $this->markdownToPlainText($markdown);

        // If no title, promote the first meaningful sentence of the text.
        if ($title === '' && $text !== '') {
            $first = preg_split('/[.!?…](?:\s|$)/u', $text, 2);
            $candidate = trim((string) ($first[0] ?? ''));
            if ($candidate !== '') {
                $title = $candidate;
                $text = trim((string) ($first[1] ?? ''));
            }
        }

        // A status with neither title nor body is empty — never publish link-only spam.
        if ($title === '' && $text === '') {
            return null;
        }

        $hasUrl = $canonicalUrl !== '' && mb_strpos($text, $canonicalUrl) !== false;
        $linkBlock = ($canonicalUrl !== '' && !$hasUrl) ? "Read more:\n" . $canonicalUrl : '';

        $header = $title;
        $body = $text;
        $sep = ($header !== '' && $body !== '') ? "\n\n" : '';

        $main = $header . $sep . $body;
        $linkSep = ($main !== '' && $linkBlock !== '') ? "\n\n" : '';

        $full = $main . $linkSep . $linkBlock;

        if (mb_strlen($full) <= $limit) {
            $trimmed = trim($full);
            return $trimmed === '' ? null : $trimmed;
        }

        // Reserve space for the link block (must stay intact).
        $reserved = ($linkBlock !== '') ? mb_strlen($linkBlock) + ($main !== '' ? 2 : 0) : 0;
        $mainBudget = $limit - $reserved;
        if ($mainBudget < 20) {
            $mainBudget = 20; // never starve the body completely; the link still fits at the end
        }
        $main = $this->truncateWordSafe($main, $mainBudget);

        $result = trim($main . (($main !== '' && $linkBlock !== '') ? "\n\n" : '') . $linkBlock);
        return $result === '' ? null : $result;
    }

    /** Character limit reported by the Mastodon instance; fallback 500. */
    public function getCharacterLimit(): int {
        if ($this->charLimit !== null) return $this->charLimit;
        $limit = 500;
        if ($this->isConfigured()) {
            try {
                [$status, $body] = $this->httpGet($this->baseUrl . '/api/v1/instance', 8);
                if ($status === 200) {
                    $json = json_decode($body, true);
                    if (is_array($json) && isset($json['configuration']['statuses']['max_characters'])) {
                        $limit = (int) $json['configuration']['statuses']['max_characters'];
                    }
                }
            } catch (Throwable $e) {
                // instance metadata unavailable -> default
            }
        }
        $limit = max(280, min(5000, $limit));
        $this->charLimit = $limit;
        return $limit;
    }

    private function normalizeVisibility(?string $visibility): string {
        if ($visibility !== null && in_array($visibility, [self::VIS_PUBLIC, self::VIS_UNLISTED, self::VIS_PRIVATE], true)) {
            return $visibility;
        }
        return $this->visibility;
    }

    // ------------------------------------------------------------------
    // Text helpers
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

    /** Convert the site's markdown subset to plain text, preserving line breaks. */
    private function markdownToPlainText(string $md): string {
        $md = (string) preg_replace('/```[a-z]*\n?/i', '', $md);           // code fences
        $md = (string) preg_replace('/`([^`]+)`/u', '$1', $md);              // inline code
        $md = (string) preg_replace('/!\[([^\]]*)\]\([^)]*\)/u', '$1', $md); // images -> alt
        $md = (string) preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $md);  // links -> label
        $md = (string) preg_replace('/^#{1,6}\s+/mu', '', $md);              // headings
        $md = (string) preg_replace('/^>\s?/mu', '', $md);                   // blockquotes
        $md = (string) preg_replace('/^\s*[-*+]\s+/mu', '• ', $md);         // list items
        $md = (string) preg_replace('/\*\*([^*]+)\*\*/u', '$1', $md);     // bold
        $md = (string) preg_replace('/__([^_]+)__/u', '$1', $md);             // bold alt
        $md = (string) preg_replace('/\*([^*]+)\*/u', '$1', $md);           // italic
        $md = (string) preg_replace('/_([^_]+)_/u', '$1', $md);               // italic alt
        $md = (string) preg_replace('/~~([^~]+)~~/u', '$1', $md);             // strikethrough
        $md = strip_tags($md);                                                // stray HTML
        $md = (string) preg_replace('/\n{3,}/u', "\n\n", $md);             // collapse blank runs
        $md = (string) preg_replace('/[ \t]+\n/u', "\n", $md);             // trailing spaces
        return trim($md);
    }

    /** Word-safe truncation: never cuts mid-word; appends ellipsis when truncated. */
    private function truncateWordSafe(string $text, int $maxLen): string {
        if (mb_strlen($text) <= $maxLen) return $text;
        $room = max(1, $maxLen - 1); // reserve 1 char for "…"
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

    private function extractStatusUrl(array $json): string {
        foreach (['url', 'uri'] as $k) {
            if (isset($json[$k]) && is_string($json[$k]) && $json[$k] !== '') {
                return $json[$k];
            }
        }
        return '';
    }

    /** Best-effort human-readable error detail from a Mastodon error body. */
    private function extractErrorDetail(string $body): string {
        $json = json_decode($body, true);
        if (is_array($json)) {
            if (isset($json['error']) && is_string($json['error'])) {
                return $this->sanitizeError(mb_substr($json['error'], 0, 200));
            }
            if (isset($json['error']) && is_array($json['error'])) {
                $parts = [];
                foreach ($json['error'] as $field => $msgs) {
                    $parts[] = $field . ': ' . implode(', ', array_map('strval', (array) $msgs));
                }
                $detail = implode('; ', $parts);
                return $this->sanitizeError(mb_substr($detail, 0, 200));
            }
        }
        $clean = $this->sanitizeError(mb_substr(trim((string) $body), 0, 200));
        return $clean;
    }

    /** Never record credentials or the Authorization header in stored/logged errors. */
    private function sanitizeError(string $message): string {
        if ($this->accessToken !== '') {
            $message = str_replace($this->accessToken, '[REDACTED]', $message);
        }
        $message = (string) preg_replace('/Authorization:\s*Bearer\s+[^\s,]+/i', 'Authorization: [REDACTED]', $message);
        $message = (string) preg_replace('/Idempotency-Key:\s*[^\s,]+/i', 'Idempotency-Key: [REDACTED]', $message);
        return mb_substr($message, 0, 300);
    }

    private function result(bool $success, $statusId, $statusUrl, int $httpStatus, bool $retryable, ?string $error): array {
        return [
            'success' => $success,
            'status_id' => $statusId,
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

    protected function httpGet(string $url, int $timeoutSec): array {
        if (is_callable(self::$httpOverride)) {
            return (self::$httpOverride)($url, [], '', 'GET');
        }
        return $this->curlRequest('GET', $url, [], '', $timeoutSec);
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
