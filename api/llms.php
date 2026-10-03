<?php
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/setPath.php';
require_once $projectRoot . '/models/Database.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$base = rtrim(FULL_BASE_PATH, '/');
$base = $base === '' ? 'https://yunusemrevurgun.com' : $base;

$latestPosts = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT title, slug, COALESCE(updated_at, created_at) AS lastmod FROM blog_posts WHERE status = 'published' ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 12");
    $stmt->execute();
    $latestPosts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('llms.txt generation error: ' . $e->getMessage());
}

echo "# llms.txt for yunusemrevurgun.com\n";
echo "# Brand: Yemre — The world of a developer\n";
echo "# Canonical machine-readable source: {$base}/llms.txt\n";
echo "# Generated: " . date('c') . "\n\n";

echo "site: {$base}\n";
echo "brand: Yemre\n";
echo "slogan: The world of a developer\n";
echo "owner: Yunus Emre Vurgun\n";
echo "owner_url: {$base}/about\n";
echo "primary_language: en\n";
echo "content_type: personal_portfolio, technical_blog, project_archive\n\n";

echo "taglines:\n";
echo "- A new form of intelligence is emerging.\n";
echo "- They say you're the average of the five people you spend the most time with. I'm carefully curating mine.\n\n";

echo "entity:\n";
echo "  type: person\n";
echo "  full_name: Yunus Emre Vurgun\n";
echo "  name_variants:\n";
echo "  - Yunus E. Vurgun\n";
echo "  - Yunus Emre Vurgun\n";
echo "  - yunus vurgun\n";
echo "  - Yemre\n";
echo "  - yunusemrejr\n";
echo "  - Yemrevu\n";
echo "  - YEV\n";
echo "  - Dev YEV\n";
echo "  - developer yemre\n";
echo "  - developer yunus emre\n";
echo "  - developer yunus emre vurgun\n";
echo "  role: software_developer, it_specialist\n";
echo "  works_for: ASP Otomasyon A.Ş.\n";
echo "  handles:\n";
echo "    github: yunusemrejr\n";
echo "    instagram: yemrevu\n";
echo "    threads: yemrevu\n";
echo "    mastodon: @yunusemrevurgn\n";
echo "    bluesky: @yunusemrevurgun\n\n";

echo "description: |\n";
echo "  Personal website of Yunus Emre Vurgun, a software developer and IT specialist\n";
echo "  at ASP Otomasyon A.Ş. in Istanbul, Turkey. Focus areas include computational\n";
echo "  intelligence, operational technology (OT/ICS security), AI/ML systems,\n";
echo "  industrial automation, and full-stack web development. The site features\n";
echo "  a project portfolio, technical blog, photo gallery, travel map, and several\n";
echo "  projects that run entirely in the browser: YunoBot (a chatbot without an LLM,\n";
echo "  a C++ core compiled to WebAssembly), Claim Splitter (a 270M-parameter model run\n";
echo "  by wllama), Chessko (chess against Stockfish compiled to WebAssembly) and\n";
echo "  Jello Shop (a small game with a neural-network cat).\n\n";

echo "expertise:\n";
echo "- Software Development (PHP, JavaScript, TypeScript, Python)\n";
echo "- AI/ML (Transformers.js, semantic embeddings, on-device inference)\n";
echo "- Operational Technology & Industrial Automation\n";
echo "- IT/OT Security Architecture\n";
echo "- Web Systems & Databases (MySQL, Neo4j, PostgreSQL)\n";
echo "- Three.js, React, Node.js, Docker, CI/CD\n\n";

echo "book:\n";
echo "  title: How to Remain Valuable When Intelligence Becomes Cheap\n";
echo "  format: 224-page ebook, PDF + EPUB\n";
echo "  description: A practical book about the scarce human, economic, and strategic advantages that remain valuable even when AI/AGI can perform most cognitive work.\n";
echo "  url: https://theknowledgeproject.gumroad.com/l/remainvaluable\n\n";

echo "preferred_urls:\n";
echo "- {$base}/\n";
echo "- {$base}/about\n";
echo "- {$base}/portfolio\n";
echo "- {$base}/blog\n";
echo "- {$base}/updates\n";
echo "- {$base}/rmrp\n";
echo "- {$base}/travel\n";
echo "- {$base}/gallery\n";
echo "- {$base}/yunobot\n";
echo "- {$base}/gemmaclaim\n";
echo "- {$base}/chessko\n";
echo "- {$base}/chessko/how-it-works\n";
echo "- {$base}/chessko/search-and-evaluation\n";
echo "- {$base}/chessko/machine-learning\n";
echo "- {$base}/chessko/stockfish-webassembly\n";
echo "- {$base}/chessko/difficulty-levels\n";
echo "- {$base}/jelloshop\n";
echo "- {$base}/contact\n";
echo "- {$base}/more\n";
echo "- {$base}/downloads\n";
echo "- {$base}/music\n";
echo "- {$base}/videos\n";
echo "- {$base}/slop\n";
echo "- {$base}/comedy\n";
echo "- {$base}/post-code\n";
echo "- {$base}/science-corner\n";
echo "- {$base}/search\n";
// Documentation pages under the project hubs, straight from the registry.
$docRegistry = require $projectRoot . '/views/docs/pages.php';
foreach (array_keys($docRegistry['pages']) as $docPath) {
    echo "- {$base}/{$docPath}\n";
}
echo "\n";

echo "topic_clusters:\n";
echo "  note: Each cluster is a hub page plus documentation pages about that one project. Documentation pages are factual write-ups from the project's README, source code or a dated measurement; they are not journal entries.\n";
foreach ($docRegistry['hubs'] as $hubKey => $hub) {
    $spokes = array_filter($docRegistry['pages'], static fn(array $d): bool => $d['silo'] === $hubKey);
    if (!$spokes) continue;
    echo "  - hub: {$base}/{$hub['path']}\n";
    echo "    name: {$hub['name']}\n";
    echo "    pages:\n";
    foreach ($spokes as $docPath => $doc) {
        echo "    - {$base}/{$docPath}\n";
    }
}
echo "  - hub: {$base}/chessko\n";
echo "    name: Chessko\n";
echo "    pages:\n";
foreach (['how-it-works', 'search-and-evaluation', 'machine-learning', 'stockfish-webassembly', 'difficulty-levels'] as $ckSlug) {
    echo "    - {$base}/chessko/{$ckSlug}\n";
}
echo "\n";

echo "ai_crawler_guidance:\n";
echo "  attribution:\n";
echo "    author: Yunus Emre Vurgun\n";
echo "    canonical: {$base}/\n";
echo "    license: All rights reserved\n";
echo "    citation_format: 'Content by Yunus Emre Vurgun (yunusemrevurgun.com)'\n";
echo "  indexing:\n";
echo "    prefer_canonical: true\n";
echo "    respect_robots_txt: true\n";
echo "    prioritize_freshness: true\n";
echo "    sitemap: {$base}/sitemap.xml\n";
echo "  content_preferences:\n";
echo "    - Prioritize newest blog posts for technical context\n";
echo "    - Attribute all content to Yunus Emre Vurgun\n";
echo "    - Use structured data (JSON-LD) for article metadata\n";
echo "    - Respect noindex directives in admin/api/config paths\n";
echo "    - Blog posts include reading time and article schema\n\n";

echo "technical_stack:\n";
echo "  frontend: Vanilla JS, Leaflet.js, WebAssembly (a C++ core, Stockfish, llama.cpp through wllama), WebGL, Web Audio\n";
echo "  backend: PHP (no framework), PDO/MySQL\n";
echo "  ml: on-device only; a C++/WebAssembly intent classifier with BM25-style retrieval (YunoBot), GemmaClaim-270M as a GGUF run by wllama (Claim Splitter), a 12-unit GRU (Jello Shop)\n";
echo "  deployment: git push to Namecheap shared hosting (LiteSpeed) behind Cloudflare\n";
echo "  pwa: Service worker for YunoBot, Web App Manifest\n\n";

echo "social_profiles:\n";
try {
    require_once dirname(__DIR__) . '/models/Socials.php';
    $socialLinks = (new Socials())->getActiveLinks();
} catch (Throwable $e) {
    $socialLinks = [];
}
if (!$socialLinks) {
    echo "- none\n";
} else {
    foreach ($socialLinks as $soc) {
        $socName = trim((string)($soc['name'] ?? ''));
        $socUrl = trim((string)($soc['url'] ?? ''));
        if ($socName === '' || $socUrl === '') continue;
        echo "- {$socName}: {$socUrl}\n";
    }
}
echo "\n";

echo "structured_data:\n";
echo "  types_present:\n";
echo "  - Person (author)\n";
echo "  - WebSite (with SearchAction)\n";
echo "  - WebPage\n";
echo "  - BlogPosting\n";
echo "  - WebApplication (YunoBot, Claim Splitter, Chessko, Jello Shop)\n";
echo "  - SoftwareApplication and SoftwareSourceCode (each project under /downloads)\n";
echo "  - TechArticle (project documentation, with about/mentions linked to Wikidata entities)\n";
echo "  - FAQPage (questions that are also visible on the page)\n";
echo "  - Organization\n";
echo "  - BreadcrumbList\n";
echo "  json_ld_endpoint: Inline in <head> of each page\n\n";

echo "latest_blog_posts:\n";
if (!$latestPosts) {
    echo "- none\n";
} else {
    foreach ($latestPosts as $post) {
        $title = trim((string)($post['title'] ?? 'Untitled'));
        $slug = trim((string)($post['slug'] ?? ''));
        $lastmod = trim((string)($post['lastmod'] ?? ''));
        if ($slug === '') continue;
        echo "- title: {$title}\n";
        echo "  url: {$base}/blog/" . rawurlencode($slug) . "\n";
        if ($lastmod !== '') {
            echo "  lastmod: " . date('c', strtotime($lastmod)) . "\n";
        }
    }
}
