<?php
/**
 * Combined feed of everything dated on the site — journal posts, updates and
 * memories merged newest-first — as Atom 1.0 (/atom.xml) or JSON Feed 1.1
 * (/feed.json) via the .htaccess rewrites. The per-section RSS feeds
 * (/blog.xml, /updates.xml, /rmrp.xml) are unchanged. Each entry carries its
 * real published and updated timestamps, so aggregators and AI retrievers
 * can tell exactly what changed and when.
 */
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/setPath.php';
require_once $projectRoot . '/models/Database.php';
require_once $projectRoot . '/models/RichText.php';

$base = rtrim(FULL_BASE_PATH, '/');
$base = $base === '' ? 'https://yunusemrevurgun.com' : $base;
$format = ($_GET['format'] ?? 'atom') === 'json' ? 'json' : 'atom';
$selfUrl = $base . ($format === 'json' ? '/feed.json' : '/atom.xml');

$items = [];
try {
    $db = Database::getInstance()->getConnection();
    $collect = static function (array $rows, callable $map) use (&$items): void {
        foreach ($rows as $row) $items[] = $map($row);
    };
    $fetch = static function (string $query) use ($db): array {
        try {
            return $db->query($query)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('combined feed query error: ' . $e->getMessage());
            return [];
        }
    };
    $collect(
        $fetch("SELECT title, slug, meta_description, excerpt, content, created_at, updated_at FROM blog_posts WHERE status = 'published' ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 30"),
        static fn(array $r): array => [
            'title' => (string)$r['title'],
            'url' => $base . '/blog/' . rawurlencode((string)$r['slug']),
            'summary' => trim((string)($r['meta_description'] ?: $r['excerpt'] ?: '')),
            'body' => (string)$r['content'],
            'published' => (int)strtotime((string)$r['created_at']),
            'updated' => (int)strtotime((string)($r['updated_at'] ?: $r['created_at'])),
            'section' => 'Journal',
        ]
    );
    $collect(
        $fetch('SELECT id, title, description, category, update_date, created_at, updated_at FROM updates ORDER BY update_date DESC, created_at DESC LIMIT 30'),
        static fn(array $r): array => [
            'title' => (string)$r['title'],
            'url' => $base . '/updates/' . (int)$r['id'],
            'summary' => RichText::plainText((string)$r['description']),
            'body' => (string)$r['description'],
            'published' => (int)strtotime((string)($r['update_date'] ?: $r['created_at'])),
            'updated' => (int)strtotime((string)($r['updated_at'] ?: $r['created_at'])),
            'section' => 'Updates',
        ]
    );
    $collect(
        $fetch('SELECT id, title, description, category, memory_date, created_at, updated_at FROM rmrp_memories ORDER BY memory_date DESC, created_at DESC LIMIT 30'),
        static fn(array $r): array => [
            'title' => (string)$r['title'],
            'url' => $base . '/rmrp/' . (int)$r['id'],
            'summary' => RichText::plainText((string)$r['description']),
            'body' => (string)$r['description'],
            'published' => (int)strtotime((string)($r['memory_date'] ?: $r['created_at'])),
            'updated' => (int)strtotime((string)($r['updated_at'] ?: $r['created_at'])),
            'section' => 'Random Memories',
        ]
    );
} catch (Throwable $e) {
    error_log('combined feed error: ' . $e->getMessage());
}
usort($items, static fn(array $a, array $b): int => $b['updated'] <=> $a['updated']);
$items = array_slice($items, 0, 40);
$feedUpdated = $items !== [] ? max(array_column($items, 'updated')) : time();

if ($format === 'json') {
    $payload = [
        'version' => 'https://jsonfeed.org/version/1.1',
        'title' => 'Yunus Emre Vurgun — Journal, Updates & Memories',
        'home_page_url' => $base . '/',
        'feed_url' => $selfUrl,
        'description' => 'Journal posts, dated updates and memories by Yunus Emre Vurgun (Yemre), newest changes first.',
        'language' => 'en-US',
        'icon' => $base . '/assets/images/pwa-icon-512.png',
        'favicon' => $base . '/assets/images/favicon-32.png',
        'authors' => [[
            'name' => 'Yunus Emre Vurgun',
            'url' => $base . '/about',
            'avatar' => $base . '/assets/images/yunus-emre-vurgun-portrait.jpg',
        ]],
        'items' => array_map(static fn(array $i): array => array_filter([
            'id' => $i['url'],
            'url' => $i['url'],
            'title' => $i['title'],
            'summary' => $i['summary'] !== '' ? $i['summary'] : null,
            'content_html' => RichText::markdown($i['body']),
            'date_published' => date('c', $i['published']),
            'date_modified' => date('c', $i['updated']),
            'authors' => [['name' => 'Yunus Emre Vurgun', 'url' => $base . '/about']],
            'tags' => [$i['section']],
            'language' => 'en-US',
        ], static fn($v): bool => $v !== null), $items),
    ];
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    $contentType = 'application/feed+json; charset=utf-8';
} else {
    $x = static fn(string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    ob_start();
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="en-US">' . "\n";
    echo '  <title>Yunus Emre Vurgun — Journal, Updates &amp; Memories</title>' . "\n";
    echo '  <subtitle>Journal posts, dated updates and memories by Yunus Emre Vurgun (Yemre), newest changes first.</subtitle>' . "\n";
    echo '  <id>' . $x($base . '/') . '</id>' . "\n";
    echo '  <updated>' . date('c', $feedUpdated) . '</updated>' . "\n";
    echo '  <link rel="alternate" type="text/html" href="' . $x($base . '/') . '"/>' . "\n";
    echo '  <link rel="self" type="application/atom+xml" href="' . $x($selfUrl) . '"/>' . "\n";
    echo '  <icon>' . $x($base . '/assets/images/favicon-32.png') . '</icon>' . "\n";
    echo '  <logo>' . $x($base . '/assets/images/pwa-icon-512.png') . '</logo>' . "\n";
    echo '  <rights>© Yunus Emre Vurgun. All rights reserved unless a linked project page states an open-source licence.</rights>' . "\n";
    echo '  <author><name>Yunus Emre Vurgun</name><uri>' . $x($base . '/about') . '</uri></author>' . "\n";
    foreach ($items as $i) {
        echo '  <entry>' . "\n";
        echo '    <title>' . $x($i['title']) . '</title>' . "\n";
        echo '    <id>' . $x($i['url']) . '</id>' . "\n";
        echo '    <link rel="alternate" type="text/html" href="' . $x($i['url']) . '"/>' . "\n";
        echo '    <published>' . date('c', $i['published']) . '</published>' . "\n";
        echo '    <updated>' . date('c', $i['updated']) . '</updated>' . "\n";
        echo '    <category term="' . $x($i['section']) . '"/>' . "\n";
        echo '    <author><name>Yunus Emre Vurgun</name><uri>' . $x($base . '/about') . '</uri></author>' . "\n";
        if ($i['summary'] !== '') echo '    <summary>' . $x($i['summary']) . '</summary>' . "\n";
        echo '    <content type="html">' . $x(RichText::markdown($i['body'])) . '</content>' . "\n";
        echo '  </entry>' . "\n";
    }
    echo '</feed>' . "\n";
    $body = (string)ob_get_clean();
    $contentType = 'application/atom+xml; charset=utf-8';
}

$etag = '"' . hash('sha256', $body) . '"';
header('Content-Type: ' . $contentType);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300, must-revalidate');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $feedUpdated) . ' GMT');
$ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime((string)$_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
if ($ifNoneMatch === $etag || ($ifNoneMatch === '' && $ifModifiedSince !== false && $ifModifiedSince >= $feedUpdated)) {
    http_response_code(304);
    exit;
}
echo $body;
