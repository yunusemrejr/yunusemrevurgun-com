<?php
/**
 * RSS 2.0 feed for the Journal (blog) — served at /blog.xml via the
 * .htaccess rewrite rule. Dynamic (always fresh from the DB), so no
 * file writes or regeneration hooks are needed.
 */
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/setPath.php';
require_once $projectRoot . '/models/Database.php';
require_once $projectRoot . '/models/RichText.php';

header('Content-Type: application/rss+xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$base = rtrim(FULL_BASE_PATH, '/');
$base = $base === '' ? 'https://yunusemrevurgun.com' : $base;

$items = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        "SELECT title, slug, COALESCE(updated_at, created_at) AS pubdate, meta_description, content
         FROM blog_posts
         WHERE status = 'published'
         ORDER BY COALESCE(updated_at, created_at) DESC
         LIMIT 25"
    );
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('blog.xml generation error: ' . $e->getMessage());
}

// Real build time = newest post change; stamping "now" would claim a
// refresh that never happened and defeat reader/CDN conditional GETs.
$feedUpdated = 0;
foreach ($items as $item) {
    $feedUpdated = max($feedUpdated, (int)strtotime((string)($item['pubdate'] ?? '')));
}
if ($feedUpdated <= 0) $feedUpdated = time();

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n";
echo '  <channel>' . "\n";
echo '    <title>Journal — Yunus Emre Vurgun</title>' . "\n";
echo '    <link>' . htmlspecialchars($base . '/blog', ENT_XML1) . '</link>' . "\n";
echo '    <description>Essays and notes on software, AI/ML, and operational technology by Yunus Emre Vurgun.</description>' . "\n";
echo '    <language>en-us</language>' . "\n";
echo '    <copyright>© Yunus Emre Vurgun</copyright>' . "\n";
echo '    <lastBuildDate>' . date('r', $feedUpdated) . '</lastBuildDate>' . "\n";
echo '    <generator>yunusemrevurgun.com</generator>' . "\n";
echo '    <ttl>60</ttl>' . "\n";
echo '    <image><url>' . htmlspecialchars($base . '/assets/images/pwa-icon-192.png', ENT_XML1) . '</url><title>Journal — Yunus Emre Vurgun</title><link>' . htmlspecialchars($base . '/blog', ENT_XML1) . '</link></image>' . "\n";
echo '    <atom:link href="' . htmlspecialchars($base . '/blog.xml', ENT_XML1) . '" rel="self" type="application/rss+xml"/>' . "\n";

foreach ($items as $item) {
    $url = $base . '/blog/' . rawurlencode($item['slug'] ?? '');
    $excerpt = trim((string) ($item['meta_description'] ?? ''));
    if ($excerpt === '') {
        $excerpt = mb_substr(trim(strip_tags((string) ($item['content'] ?? ''))), 0, 300);
    }
    echo '    <item>' . "\n";
    echo '      <title>' . htmlspecialchars(($item['title'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</title>' . "\n";
    echo '      <link>' . htmlspecialchars($url, ENT_XML1) . '</link>' . "\n";
    echo '      <guid isPermaLink="true">' . htmlspecialchars($url, ENT_XML1) . '</guid>' . "\n";
    echo '      <pubDate>' . date('r', strtotime($item['pubdate'] ?? 'now')) . '</pubDate>' . "\n";
    echo '      <dc:creator>Yunus Emre Vurgun</dc:creator>' . "\n";
    echo '      <description>' . htmlspecialchars(RichText::markdown($excerpt), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</description>' . "\n";
    echo '    </item>' . "\n";
}

echo '  </channel>' . "\n";
echo '</rss>' . "\n";

$body = (string)ob_get_clean();
$etag = '"' . hash('sha256', $body) . '"';
header('Cache-Control: public, max-age=300, must-revalidate');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $feedUpdated) . ' GMT');
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
echo $body;
