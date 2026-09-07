<?php
require_once dirname(__DIR__) . '/models/Sitemap.php';
header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
try {
    $xml = (new Sitemap())->renderXML();
    $etag = '"' . hash('sha256', $xml) . '"';
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('ETag: ' . $etag);
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    echo $xml;
} catch (Throwable $error) {
    error_log('Sitemap rendering failed: ' . $error->getMessage());
    http_response_code(503);
    header('Retry-After: 300');
    header('Cache-Control: no-store');
    echo '<?xml version="1.0" encoding="UTF-8"?><error>Sitemap temporarily unavailable</error>';
}
