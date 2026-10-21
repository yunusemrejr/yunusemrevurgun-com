<?php
/**
 * /llms.txt — a Markdown map of this site for language models and AI agents,
 * in the llmstxt.org format: H1, a blockquote summary, factual detail, then
 * ## sections of `- [Title](url): description` links. The full text of the
 * writing lives in /llms-full.txt; canonical HTML pages remain authoritative.
 */
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/setPath.php';
require_once $projectRoot . '/models/Database.php';

$base = rtrim(FULL_BASE_PATH, '/');
$base = $base === '' ? 'https://yunusemrevurgun.com' : $base;

/** One llms.txt link line: `- [Title](url): description` (description optional). */
$link = static function (string $title, string $url, string $description = ''): string {
    $title = trim(str_replace(['[', ']'], '', $title));
    $description = trim((string)preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (mb_strlen($description) > 220) {
        $description = rtrim((string)preg_replace('/\s+\S*$/u', '', mb_substr($description, 0, 220)), " \t,;:—-") . '…';
    }
    return '- [' . $title . '](' . $url . ')' . ($description !== '' ? ': ' . $description : '') . "\n";
};

$latestPosts = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT title, slug, meta_description, excerpt, COALESCE(updated_at, created_at) AS lastmod FROM blog_posts WHERE status = 'published' ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 15");
    $stmt->execute();
    $latestPosts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('llms.txt generation error: ' . $e->getMessage());
}

$socialLinks = [];
try {
    require_once $projectRoot . '/models/Socials.php';
    $socialLinks = (new Socials())->getActiveLinks();
} catch (Throwable $e) {
    error_log('llms.txt socials error: ' . $e->getMessage());
}

ob_start();

echo "# Yunus Emre Vurgun (Yemre) — yunusemrevurgun.com\n\n";
echo "> Personal website of Yunus Emre Vurgun, a software developer and IT specialist at ASP Otomasyon A.Ş. in Istanbul, Turkey: a project portfolio, a technical journal on AI/ML and operational technology, and several complete programs that run entirely in the visitor's browser (a chatbot without an LLM, a claim-analysis model, chess against Stockfish, and a coffee-shop game with a neural-network cat).\n\n";

echo "Brand: Yemre — \"The world of a developer\". Tagline: \"A new form of intelligence is emerging.\"\n\n";
echo "The author is one person, Yunus Emre Vurgun, also written Yunus E. Vurgun and known online as Yemre, YEV, yunusemrejr (GitHub) and Yemrevu (Instagram/Threads). He works as a software developer and IT specialist at ASP Otomasyon A.Ş. (Istanbul, Turkey), focusing on computational intelligence, operational technology and ICS/OT security, AI/ML systems, industrial automation and full-stack web development (PHP, JavaScript/TypeScript, Python; MySQL, PostgreSQL, Neo4j; Three.js, React, Node.js, Docker, CI/CD).\n\n";
echo "He is the author of the 224-page ebook \"How to Remain Valuable When Intelligence Becomes Cheap\" (PDF + EPUB, via Gumroad: https://theknowledgeproject.gumroad.com/l/remainvaluable), a practical guide to the human, economic and strategic advantages that stay scarce when AI can perform most cognitive work.\n\n";
echo "Citation: attribute content to \"Yunus Emre Vurgun (yunusemrevurgun.com)\" and link the canonical page URL. Content is all rights reserved unless a project page states an open-source licence (the /downloads projects link their GitHub repositories and licences). Structured data (JSON-LD: Person, WebSite, WebPage, BlogPosting, TechArticle, WebApplication, SoftwareApplication, FAQPage, BreadcrumbList) is embedded in each page's <head> and is the preferred source for dates, authorship and article metadata. Freshness: /sitemap.xml is a sitemap index whose children carry real per-page lastmod timestamps; feeds are listed at the end of this file. In the browser, YunoBot answers in English or Turkish — try \"Who is Yunus?\", \"What is Chessko?\" or \"Download projeleri neler?\".\n\n";
echo "The full text of the journal, documentation and project pages is concatenated in [llms-full.txt]({$base}/llms-full.txt) for single-file ingestion.\n\n";

echo "## Core pages\n\n";
echo $link('Home', "{$base}/", 'Yunus Emre Vurgun (Yemre), software developer and IT specialist in Istanbul. Explore projects, writing, and work in AI/ML and operational technology.');
echo $link('About', "{$base}/about", 'About Yunus Emre Vurgun (Yemre, YEV, yunusemrejr) — software developer and IT specialist in Istanbul, building AI/ML systems and operational technology solutions.');
echo $link('Portfolio', "{$base}/portfolio", 'Project archive by Yunus Emre Vurgun (Yemre): AI/ML, operational technology, industrial automation and web projects, each with the technologies it uses.');
echo $link('Journal (blog)', "{$base}/blog", 'Long-form notes by Yunus Emre Vurgun (Yemre, YEV) on AI capability trends, model releases, free internet access, software architecture and post-code engineering.');
echo $link('Contact', "{$base}/contact", 'Send a message to Yunus Emre Vurgun (Yemre, yunusemrejr) for projects, collaborations, or technical consulting.');
echo $link('Explore', "{$base}/more", 'Every project, archive and experiment on the site in one index.');
echo $link('Sitemap (HTML)', "{$base}/sitemap", 'Every page on yunusemrevurgun.com: main pages, archives, projects you can run, project documentation and legal pages.');
echo "\n";

echo "## Programs that run in your browser\n\n";
echo $link('YunoBot', "{$base}/yunobot", 'YunoBot is an on-device chatbot without an LLM: a C++/WebAssembly core answers in English or Turkish in your browser and links its sources.');
echo $link('Claim Splitter (GemmaClaim)', "{$base}/gemmaclaim", 'Paste a claim and a 270M-parameter model lists its premises, hidden assumptions and weak points in your browser. The tool never uploads your text.');
echo $link('Chessko', "{$base}/chessko", 'Play Chessko, a free browser chess game with wobbling jelly pieces. Seven levels, from a wobbly beginner bot to Stockfish 19 running as WebAssembly. No sign-up.');
echo $link('Jello Shop', "{$base}/jelloshop", 'A calm browser game: walk Jell-omo, a 3D jelly in a baseball cap, around a hand-drawn coffee shop, collect beans and pet a cat run by a tiny neural network.');
echo "\n";

echo "## Project documentation\n\n";
echo "Factual write-ups taken from each project's README, source code or a dated measurement. Each hub page is the program itself; the pages below explain how it works.\n\n";
$docRegistry = require $projectRoot . '/views/docs/pages.php';
foreach ($docRegistry['pages'] as $docPath => $doc) {
    echo $link((string)($doc['h1'] ?? $doc['title']), "{$base}/{$docPath}", (string)($doc['description'] ?? ''));
}
$chesskoArticles = require $projectRoot . '/views/chessko/articles.php';
foreach ($chesskoArticles as $slug => $article) {
    echo $link((string)$article['h1'], "{$base}/chessko/{$slug}", (string)$article['description']);
}
echo "\n";

echo "## Open-source downloads\n\n";
echo $link('Downloads index', "{$base}/downloads", 'Eight free, open-source Linux projects by Yunus Emre Vurgun: two games, an artificial-life rat, two coding agents, a local AI agent, a finance assistant and a disk cleaner.');
foreach ((require $projectRoot . '/includes/downloads_catalog.php') as $item) {
    echo $link((string)$item['title'], "{$base}/downloads/" . $item['slug'], (string)$item['description']);
}
echo "\n";

if ($latestPosts !== []) {
    echo "## Journal — latest posts\n\n";
    foreach ($latestPosts as $post) {
        $slug = trim((string)($post['slug'] ?? ''));
        if ($slug === '') continue;
        $desc = trim((string)($post['meta_description'] ?? '')) ?: trim((string)($post['excerpt'] ?? ''));
        echo $link((string)($post['title'] ?? 'Untitled'), "{$base}/blog/" . rawurlencode($slug), $desc);
    }
    echo "\n";
}

echo "## Archives, media and knowledge\n\n";
echo $link('Updates', "{$base}/updates", 'Dated short notes and rapid changes, newest first.');
echo $link('Random Memories for Random People', "{$base}/rmrp", 'Short dated memories and fragments.');
echo $link('Gallery', "{$base}/gallery", 'Visual archive by Yunus Emre Vurgun — projects, processes, and technical explorations.');
echo $link('Travel map', "{$base}/travel", 'An interactive map of places Yunus Emre Vurgun has been, with location photos and travel statistics, starting from Istanbul.');
echo $link('Music', "{$base}/music", 'Listen to audio recordings shared by Yunus Emre Vurgun, with track details and links to music platforms.');
echo $link('Videos', "{$base}/videos", 'Videos by Yunus Emre Vurgun: direct uploads plus YouTube and Odysee clips.');
echo $link('Post-Code', "{$base}/post-code", 'Computing, mathematics and software foundations for the post-code era.');
echo $link('Science Corner', "{$base}/science-corner", 'Physics, mathematics and computer science notes and biographies.');
echo $link('Quality 3D AI Slop', "{$base}/slop", 'Interactive 3D experiments.');
echo $link('Comedy', "{$base}/comedy", 'Shower thoughts and memes.');
echo $link('Search', "{$base}/search", 'Search the whole site: journal, updates, memories, projects and documentation.');
echo "\n";

if ($socialLinks !== []) {
    echo "## Elsewhere (official profiles)\n\n";
    foreach ($socialLinks as $soc) {
        $name = trim((string)($soc['name'] ?? ''));
        $url = trim((string)($soc['url'] ?? ''));
        if ($name === '' || $url === '') continue;
        echo $link($name, $url);
    }
    echo "\n";
}

echo "## Machine-readable endpoints\n\n";
echo $link('Sitemap index (XML)', "{$base}/sitemap.xml", 'Index of sitemap-pages.xml, sitemap-posts.xml, sitemap-images.xml and sitemap-videos.xml, with real lastmod timestamps.');
echo $link('llms-full.txt', "{$base}/llms-full.txt", 'Full text of the journal, documentation and project pages in one Markdown file.');
echo $link('Journal feed (RSS)', "{$base}/blog.xml");
echo $link('Updates feed (RSS)', "{$base}/updates.xml");
echo $link('Random Memories feed (RSS)', "{$base}/rmrp.xml");
echo $link('Combined feed (Atom)', "{$base}/atom.xml", 'Journal, updates and memories merged, newest first, with per-entry updated timestamps.');
echo $link('Combined feed (JSON Feed 1.1)', "{$base}/feed.json");
echo $link('robots.txt', "{$base}/robots.txt", 'Crawl rules. Search and AI crawlers are welcome on all public content.');
echo "\n";

echo "## Optional\n\n";
echo $link('Privacy policy', "{$base}/privacy");
echo $link('Terms of use', "{$base}/terms");
echo $link('Cookie policy', "{$base}/cookies");
echo "\n";
echo "<!-- Generated dynamically from the same database as the site. Canonical: {$base}/llms.txt -->\n";

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
