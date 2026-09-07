<?php
/**
 * RSS 2.0 feed for the Updates log — served at /updates.xml via the
 * .htaccess rewrite rule below. Dynamic (always fresh from the DB), so no
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
        'SELECT id, title, description, update_date, category, importance, created_at, updated_at
         FROM updates
         ORDER BY update_date DESC, created_at DESC
         LIMIT 25'
    );
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('updates.xml generation error: ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
echo '  <channel>' . "\n";
echo '    <title>Updates — Yunus Emre Vurgun</title>' . "\n";
echo '    <link>' . htmlspecialchars($base . '/updates', ENT_XML1) . '</link>' . "\n";
echo '    <description>Short notes, timestamped entries, and rapid changes by Yunus Emre Vurgun.</description>' . "\n";
echo '    <language>en-us</language>' . "\n";
echo '    <lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
echo '    <generator>yunusemrevurgun.com</generator>' . "\n";
echo '    <atom:link href="' . htmlspecialchars($base . '/updates.xml', ENT_XML1) . '" rel="self" type="application/rss+xml"/>' . "\n";

foreach ($items as $item) {
    $url = $base . '/updates/' . (int) $item['id'];
    echo '    <item>' . "\n";
    echo '      <title>' . htmlspecialchars(($item['title'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</title>' . "\n";
    echo '      <link>' . htmlspecialchars($url, ENT_XML1) . '</link>' . "\n";
    echo '      <guid isPermaLink="true">' . htmlspecialchars($url, ENT_XML1) . '</guid>' . "\n";
    echo '      <pubDate>' . date('r', strtotime($item['update_date'] ?? $item['created_at'] ?? 'now')) . '</pubDate>' . "\n";
    if (!empty($item['category'])) {
        echo '      <category>' . htmlspecialchars($item['category'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</category>' . "\n";
    }
    echo '      <description>' . htmlspecialchars(RichText::markdown($item['description'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</description>' . "\n";
    echo '    </item>' . "\n";
}

echo '  </channel>' . "\n";
echo '</rss>' . "\n";
