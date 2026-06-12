<?php
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/setPath.php';
require_once $projectRoot . '/models/Database.php';
require_once $projectRoot . '/models/Sitemap.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$publicSitemapPath = $projectRoot . '/public/sitemap.xml';
$rootSitemapPath = $projectRoot . '/sitemap.xml';

try {
    $sitemap = new Sitemap();
    $sitemap->generateSitemapXML();

    $path = file_exists($publicSitemapPath) ? $publicSitemapPath : $rootSitemapPath;
    if (file_exists($path)) {
        readfile($path);
        exit;
    }
} catch (Throwable $e) {
    error_log('Sitemap generation error: ' . $e->getMessage());
}

$baseUrl = rtrim(FULL_BASE_PATH, '/');
if ($baseUrl === '') {
    $baseUrl = 'https://yunusemrevurgun.com';
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '  <url>' . "\n";
echo '    <loc>' . htmlspecialchars($baseUrl . '/', ENT_XML1) . '</loc>' . "\n";
echo '    <lastmod>' . date('c') . '</lastmod>' . "\n";
echo '    <changefreq>daily</changefreq>' . "\n";
echo '    <priority>1.0</priority>' . "\n";
echo '  </url>' . "\n";
echo '</urlset>';
