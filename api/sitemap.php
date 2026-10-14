<?php
require_once dirname(__DIR__) . '/models/Sitemap.php';
header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
try {
    $sitemap = new Sitemap();
    // /sitemap.xml is the index; children arrive as ?type=pages|posts|images|videos
    // (see the .htaccess rewrites). No type keeps the combined urlset for
    // anything still requesting this script directly — .htaccess 301s those.
    $type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
    $xml = match ($type) {
        'index' => $sitemap->renderIndex(),
        'pages', 'posts', 'images', 'videos' => $sitemap->renderGroup($type),
        default => $sitemap->renderXML(),
    };
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
