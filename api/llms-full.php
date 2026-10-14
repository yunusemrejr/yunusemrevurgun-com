<?php
/**
 * /llms-full.txt — the site's writing in one Markdown file for LLM ingestion
 * (companion to /llms.txt). Canonical HTML pages remain authoritative; every
 * section names its source URL and dates so models can cite precisely.
 */
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/setPath.php';
require_once $projectRoot . '/models/Database.php';
require_once $projectRoot . '/models/RichText.php';

$base = rtrim(FULL_BASE_PATH, '/');
$base = $base === '' ? 'https://yunusemrevurgun.com' : $base;

/**
 * Stored content as readable text. Journal/update bodies are Markdown
 * source (already model-friendly) and pass through with paragraphs intact;
 * documentation bodies are HTML and are flattened with paragraph breaks.
 */
$text = static function (?string $source): string {
    $plain = str_replace(["\r\n", "\r"], "\n", (string)$source);
    if (preg_match('/<(p|h[1-6]|li|tr|div|table)[\s>]/i', $plain)) {
        $plain = preg_replace('#</(p|li|h[1-6]|tr|div|table|thead|tbody)>#i', "\n\n", $plain);
        $plain = preg_replace('#<br\s*/?>#i', "\n", (string)$plain);
        $plain = preg_replace('#<li[^>]*>#i', '- ', (string)$plain);
        $plain = html_entity_decode(strip_tags((string)$plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $plain = (string)preg_replace('/[ \t]+/', ' ', $plain);
    return trim((string)preg_replace("/\n{3,}/", "\n\n", $plain));
};

$db = null;
try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    error_log('llms-full.txt DB error: ' . $e->getMessage());
}
$rows = static function (string $query) use ($db): array {
    if ($db === null) return [];
    try {
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('llms-full.txt query error: ' . $e->getMessage());
        return [];
    }
};

ob_start();

echo "# Yunus Emre Vurgun (Yemre) — full text\n\n";
echo "> Complete text of yunusemrevurgun.com's journal, project documentation and archives, concatenated for language-model ingestion. Author of everything below unless stated otherwise: Yunus Emre Vurgun. Cite as \"Yunus Emre Vurgun (yunusemrevurgun.com)\" with the per-item URL. Index with descriptions: {$base}/llms.txt\n\n";
echo "Yunus Emre Vurgun (also Yunus E. Vurgun; online: Yemre, YEV, yunusemrejr, Yemrevu) is a software developer and IT specialist at ASP Otomasyon A.Ş. in Istanbul, Turkey. Focus: computational intelligence, operational technology (OT/ICS security), AI/ML systems, industrial automation, full-stack web development. Author of the 224-page ebook \"How to Remain Valuable When Intelligence Becomes Cheap\" (PDF + EPUB, https://theknowledgeproject.gumroad.com/l/remainvaluable). Browser programs on this site: YunoBot ({$base}/yunobot, a chatbot without an LLM — C++/WebAssembly), Claim Splitter ({$base}/gemmaclaim, a 270M-parameter model via wllama), Chessko ({$base}/chessko, Stockfish 19 as WebAssembly), Jello Shop ({$base}/jelloshop, a game whose cat is a small neural network).\n\n";

// ---- Journal (full text)
$posts = $rows("SELECT title, slug, content, excerpt, meta_description, created_at, updated_at FROM blog_posts WHERE status = 'published' ORDER BY COALESCE(updated_at, created_at) DESC");
if ($posts !== []) {
    echo "---\n\n# Journal\n\n";
    foreach ($posts as $post) {
        $slug = trim((string)($post['slug'] ?? ''));
        if ($slug === '') continue;
        echo "## " . trim((string)$post['title']) . "\n\n";
        echo "URL: {$base}/blog/" . rawurlencode($slug) . "\n";
        echo "Published: " . date('c', (int)strtotime((string)$post['created_at'])) . "\n";
        echo "Updated: " . date('c', (int)strtotime((string)($post['updated_at'] ?? $post['created_at']))) . "\n\n";
        echo $text((string)$post['content']) . "\n\n";
    }
}

// ---- Project documentation (full text)
$docRegistry = require $projectRoot . '/views/docs/pages.php';
echo "---\n\n# Project documentation\n\n";
foreach ($docRegistry['pages'] as $docPath => $doc) {
    echo "## " . (string)($doc['h1'] ?? $doc['title']) . "\n\n";
    echo "URL: {$base}/{$docPath}\n";
    if (!empty($doc['published'])) echo "Published: {$doc['published']}\n";
    if (!empty($doc['modified'])) echo "Updated: {$doc['modified']}\n";
    echo "\n" . $text((string)($doc['answer'] ?? '')) . "\n\n";
    foreach (($doc['facts'] ?? []) as $fact) {
        echo '- ' . $text((string)$fact[0]) . ': ' . $text((string)$fact[1]) . "\n";
    }
    if (!empty($doc['facts'])) echo "\n";
    foreach (($doc['sections'] ?? []) as $section) {
        echo "### " . $text((string)$section[0]) . "\n\n" . $text((string)$section[1]) . "\n\n";
    }
    foreach (($doc['faq'] ?? []) as $qa) {
        echo 'Q: ' . trim((string)$qa[0]) . "\nA: " . trim((string)$qa[1]) . "\n\n";
    }
}

// ---- Chessko explainers (full text)
$chesskoArticles = require $projectRoot . '/views/chessko/articles.php';
echo "---\n\n# Chessko documentation\n\n";
foreach ($chesskoArticles as $slug => $article) {
    echo "## " . (string)$article['h1'] . "\n\n";
    echo "URL: {$base}/chessko/{$slug}\n";
    echo "Published: {$article['published']}\nUpdated: {$article['modified']}\n\n";
    echo trim((string)$article['lead']) . "\n\n";
    foreach (($article['sections'] ?? []) as $section) {
        echo "### " . $text((string)$section[0]) . "\n\n" . $text(str_replace('{{base}}', $base . '/', (string)$section[1])) . "\n\n";
    }
    foreach (($article['faq'] ?? []) as $qa) {
        echo 'Q: ' . trim((string)$qa[0]) . "\nA: " . trim((string)$qa[1]) . "\n\n";
    }
}

// ---- Downloads catalog
echo "---\n\n# Open-source downloads\n\n";
echo "Index: {$base}/downloads\n\n";
foreach ((require $projectRoot . '/includes/downloads_catalog.php') as $item) {
    echo "## " . (string)$item['title'] . "\n\n";
    echo "URL: {$base}/downloads/" . $item['slug'] . "\n";
    echo 'Kind: ' . (string)$item['kind'] . ' · Language: ' . (string)$item['language']
        . (!empty($item['license']) ? ' · Licence: ' . (string)$item['license'] : '')
        . ' · Platforms: ' . implode(', ', (array)$item['platforms']) . "\n";
    echo 'Source: https://github.com/' . (string)$item['repo'] . "\n\n";
    echo trim((string)$item['description']) . "\n\n";
}

// ---- Updates & memories (latest)
foreach ([
    ['Updates', 'updates', "SELECT id, title, description, update_date AS dated, COALESCE(updated_at, created_at) AS modified FROM updates ORDER BY update_date DESC, created_at DESC LIMIT 30"],
    ['Random Memories for Random People', 'rmrp', "SELECT id, title, description, memory_date AS dated, COALESCE(updated_at, created_at) AS modified FROM rmrp_memories ORDER BY memory_date DESC, created_at DESC LIMIT 30"],
] as [$heading, $section, $query]) {
    $items = $rows($query);
    if ($items === []) continue;
    echo "---\n\n# {$heading}\n\n";
    foreach ($items as $item) {
        echo "## " . trim((string)$item['title']) . "\n\n";
        echo "URL: {$base}/{$section}/" . (int)$item['id'] . "\n";
        echo "Date: " . date('c', (int)strtotime((string)($item['dated'] ?? $item['modified']))) . "\n\n";
        echo $text((string)$item['description']) . "\n\n";
    }
}

echo "<!-- End. Canonical pages: {$base}/ · Index: {$base}/llms.txt -->\n";

$body = (string)ob_get_clean();
$etag = '"' . hash('sha256', $body) . '"';
header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300, must-revalidate');
header('ETag: ' . $etag);
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
echo $body;
