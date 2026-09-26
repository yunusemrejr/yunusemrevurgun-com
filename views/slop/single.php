<?php
/**
 * Public — single Quality 3D AI Slop page.
 * Serves the uploaded HTML file at /slop/<slug> with SEO metadata and the
 * go-home button injected at serve time. The stored file stays untouched.
 */
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/models/Slop.php';

$slop = $GLOBALS['current_slop'] ?? null;
if (!is_array($slop) || empty($slop['slug']) || empty($slop['filename'])) {
    http_response_code(404);
    require_once dirname(__DIR__, 2) . '/views/404.php';
    return;
}

$path = Slop::storedFilePath($slop['filename']);
if ($path === false) {
    error_log('Slop file missing for slug: ' . $slop['slug']);
    http_response_code(404);
    require_once dirname(__DIR__, 2) . '/views/404.php';
    return;
}

$mtime = filemtime($path);
$lastModified = gmdate('D, d M Y H:i:s', $mtime !== false ? $mtime : time()) . ' GMT';
header('Content-Type: text/html; charset=utf-8');
header('Last-Modified: ' . $lastModified);
header('Cache-Control: public, max-age=300, must-revalidate');
if (trim($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '') === $lastModified) {
    http_response_code(304);
    exit;
}

$canonical = rtrim(FULL_BASE_PATH, '/') . '/slop/' . rawurlencode($slop['slug']);
$html = file_get_contents($path);
if ($html === false) {
    http_response_code(404);
    require_once dirname(__DIR__, 2) . '/views/404.php';
    return;
}
echo Slop::injectSeoAndHomeButton($html, $slop, $canonical);
