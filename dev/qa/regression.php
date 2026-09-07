<?php
/** Isolated regressions: php dev/qa/regression.php. Never uses production DB. */
putenv('MODE=development');
putenv('DB_CONNECTION=sqlite');
putenv('DB_NAME=:memory:');
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../../models/HtmlSanitizer.php';
require_once __DIR__ . '/../../models/Sitemap.php';
$checks = 0;
function check(bool $ok, string $name): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $name);
    $checks++;
}
$html = HtmlSanitizer::clean('<h2>Öğrenme</h2><p>Read <a href="https://example.com" target="_blank" onclick="bad()">more</a>.</p><script>bad()</script><svg onload="bad()"><a href="javascript:bad()">bad</a></svg><iframe srcdoc="bad"></iframe>');
check(str_contains($html, 'Öğrenme'), 'Unicode content survives');
check(!preg_match('/script|svg|iframe|onclick|srcdoc/', $html), 'Executable tags and attributes removed');
check(str_contains($html, 'rel="noopener noreferrer"'), 'External window protection');
foreach (['javascript:alert(1)', "java\nscript:alert(1)", 'data:text/html,bad', '//evil.example', 'https:\\evil.example'] as $url) {
    $safe = HtmlSanitizer::clean('<a href="' . htmlspecialchars($url, ENT_QUOTES) . '">x</a>');
    check(!str_contains($safe, 'href='), 'Unsafe link stripped');
}
$html = HtmlSanitizer::clean('<table><tr><th>Title</th><td><code>value</code></td></tr></table><img src="/uploads/a.jpg" width="100" height="200" alt="Photo">');
check(str_contains($html, '<table>') && str_contains($html, '<code>'), 'Article formatting preserved');
check(str_contains($html, 'loading="lazy"') && str_contains($html, 'alt="Photo"'), 'Images keep descriptions and lazy loading');
$db = Database::getInstance()->getConnection();
$db->exec('CREATE TABLE blog_posts (slug TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
$db->exec("INSERT INTO blog_posts VALUES ('public-post', 'published', '2026-01-01', '2026-02-01'), ('private-draft', 'draft', '2026-01-01', NULL)");
$sitemap = new Sitemap();
$xml = $sitemap->renderXML();
$doc = new DOMDocument();
check($doc->loadXML($xml), 'Sitemap is valid XML');
check(str_contains($xml, '/blog/public-post'), 'Published post included');
check(!str_contains($xml, 'private-draft'), 'Draft excluded');
check(!str_contains($xml, '<priority>') && !str_contains($xml, '<changefreq>'), 'Unsupported sitemap hints removed');
check(str_contains($xml, '2026-02-01'), 'Real article modification date');
$before = hash_file('sha256', __DIR__ . '/../../sitemap.xml');
check($sitemap->generateSitemapXML(), 'Local snapshot hook works');
check(hash_file('sha256', __DIR__ . '/../../sitemap.xml') === $before, 'Local generation leaves production snapshot untouched');
require_once __DIR__ . '/../../models/Travel.php';
$travel = new Travel();
$db->exec("INSERT INTO travel_locations (id, country, city, lat, lng) VALUES (1, 'Test country', 'Test city', 1, 2)");
$db->exec("INSERT INTO travel_location_images (location_id, filename) VALUES (1, 'a.jpg'), (1, 'b.jpg')");
check((int)$travel->getAllLocations()[0]['image_count'] === 2, 'Travel counts grouped in one query');
check(count($travel->getImagesGroupedByLocation()[1]) === 2, 'Travel images grouped');
$_SERVER['REQUEST_URI'] = '/admin/blog/edit?id=1';
$page = 'blog-edit';
require __DIR__ . '/../../views/admin/includes/admin-scripts.php';
check(getCurrentAdminPageContext() === 'blog-edit', 'Editor context is not duplicated');
check(in_array('admin-editor.js', getRequiredAdminModules(), true), 'Blog editor module loads');
echo "PASS: $checks regression checks\n";
