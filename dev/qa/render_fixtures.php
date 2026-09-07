<?php
/** Render actual published content without touching a persistent database. */
putenv('MODE=development');
putenv('DB_CONNECTION=sqlite');
putenv('DB_NAME=:memory:');
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once __DIR__ . '/../../models/Blog.php';
$db = Database::getInstance()->getConnection();
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT)');
$db->exec('CREATE TABLE tracker_codes (id INTEGER PRIMARY KEY, code TEXT, is_active INTEGER)');
$db->exec("INSERT INTO users VALUES (1, 'Fixture author')");
$blog = new Blog();
$insert = $db->prepare('INSERT INTO blog_posts (title, slug, content, excerpt, author_id, status) VALUES (?, ?, ?, ?, 1, ?)');
$insert->execute(['A <safe> title & Ö', 'public-fixture', '<h2>Article heading</h2><p>Body <strong>formatting</strong>.</p><script>bad()</script>', 'A real excerpt.', 'published']);
$insert->execute(['Private draft fixture', 'private-fixture', 'Private content', 'Private excerpt', 'draft']);
ob_start();
require __DIR__ . '/../../views/home.php';
$home = ob_get_clean();
$checks = 0;
function verify(bool $ok, string $name): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $name);
    $checks++;
}
verify(str_contains($home, 'Latest writing'), 'Published writing appears on homepage');
verify(str_contains($home, 'A &lt;safe&gt; title &amp; Ö'), 'Homepage escapes published titles');
verify(!str_contains($home, 'Private draft fixture'), 'Homepage excludes drafts');
$GLOBALS['current_post'] = $blog->getPublishedPost('public-fixture');
$_SERVER['REQUEST_URI'] = '/blog/public-fixture';
ob_start();
require __DIR__ . '/../../views/blog-post.php';
$article = ob_get_clean();
verify(!str_contains($article, '<script>bad()'), 'Article removes executable content');
verify(str_contains($article, '<strong>formatting</strong>'), 'Article preserves semantic formatting');
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($article);
$xpath = new DOMXPath($dom);
verify($xpath->query('//main')->length === 1 && $xpath->query('//h1')->length === 1, 'Article has one main and primary heading');
verify($xpath->query('//link[@rel="canonical"]')->length === 1, 'Article has a single canonical');
verify($xpath->query('//meta[@property="og:type" and @content="article"]')->length === 1, 'Article sharing type is retained');
foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
    json_decode($script->textContent, true, 512, JSON_THROW_ON_ERROR);
}
verify(true, 'Article structured data parses');
echo "PASS: $checks content rendering checks\n";
