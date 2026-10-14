<?php
require_once __DIR__ . '/../config/setPath.php';

/**
 * IndexNow (https://www.indexnow.org): one POST notifies Bing, Yandex,
 * Naver, Seznam and Yep that URLs were added, updated or deleted, so they
 * recrawl promptly instead of on their own schedule. ChatGPT search reads
 * Bing's index, so this also shortens AI-answer freshness lag. Google does
 * not participate; it discovers the same changes through the sitemap's
 * <lastmod> values and the feeds.
 *
 * Ownership is proven by the key file at the site root (<key>.txt whose
 * body is the key). The key is discovered from that file rather than
 * hard-coded, so rotating the file rotates the integration.
 */
class IndexNow {
    /** @return array{key:string, location:string}|null */
    public static function keyFile(): ?array {
        $best = null;
        foreach (glob(dirname(__DIR__) . '/*.txt') ?: [] as $file) {
            $stem = basename($file, '.txt');
            if (!preg_match('/^[a-z0-9-]{8,128}$/i', $stem)) continue;
            if (trim((string)@file_get_contents($file)) !== $stem) continue;
            if ($best === null || filemtime($file) > filemtime($best)) $best = $file;
        }
        if ($best === null) return null;
        $key = basename($best, '.txt');
        return ['key' => $key, 'location' => rtrim(FULL_BASE_PATH, '/') . '/' . $key . '.txt'];
    }

    /**
     * Submit up to 10,000 same-host URLs. Fire-and-forget: short timeout,
     * failures are logged, never thrown into an admin save path. Returns the
     * HTTP status (200/202 = accepted) or 0 when skipped/failed.
     *
     * @param list<string> $urls absolute URLs on this host
     */
    public static function submit(array $urls): int {
        $urls = array_values(array_unique(array_filter($urls, static fn($u): bool => is_string($u) && $u !== '')));
        if ($urls === []) return 0;
        if (getenv('MODE') === 'development' || PHP_SAPI === 'cli-server') return 0;
        $host = parse_url(FULL_BASE_PATH, PHP_URL_HOST);
        if (!$host || preg_match('/^(localhost|127\.0\.0\.1|\[::1\])$/i', $host)) return 0;
        $keyFile = self::keyFile();
        if ($keyFile === null) {
            error_log('IndexNow: no key file at site root; submission skipped.');
            return 0;
        }
        $payload = json_encode([
            'host' => $host,
            'key' => $keyFile['key'],
            'keyLocation' => $keyFile['location'],
            'urlList' => array_slice($urls, 0, 10000),
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) return 0;

        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.indexnow.org/indexnow');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 4,
            ]);
            curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($status === 0 && $error !== '') error_log('IndexNow: ' . $error);
            elseif (!in_array($status, [200, 202], true)) error_log('IndexNow: unexpected HTTP ' . $status);
            return $status;
        }

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json; charset=utf-8\r\n",
            'content' => $payload,
            'timeout' => 4,
            'ignore_errors' => true,
        ]]);
        $result = @file_get_contents('https://api.indexnow.org/indexnow', false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', $header, $m)) $status = (int)$m[1];
        }
        if ($result === false && $status === 0) error_log('IndexNow: request failed.');
        return $status;
    }
}
