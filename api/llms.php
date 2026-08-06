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
echo "  a project portfolio, technical blog, photo gallery, travel map, and YunoBot\n";
echo "  — a browser-based AI assistant running entirely client-side via Transformers.js.\n\n";

echo "expertise:\n";
echo "- Software Development (PHP, JavaScript, TypeScript, Python)\n";
echo "- AI/ML (Transformers.js, semantic embeddings, on-device inference)\n";
echo "- Operational Technology & Industrial Automation\n";
echo "- IT/OT Security Architecture\n";
echo "- Web Systems & Databases (MySQL, Neo4j, PostgreSQL)\n";
echo "- Three.js, React, Node.js, Docker, CI/CD\n\n";

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
echo "- {$base}/contact\n";
echo "- {$base}/more\n";
echo "- {$base}/downloads\n";
echo "- {$base}/music\n";
echo "- {$base}/videos\n";
echo "- {$base}/comedy\n";
echo "- {$base}/post-code\n";
echo "- {$base}/science-corner\n";
echo "- {$base}/search\n\n";

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
echo "  frontend: Vanilla JS, Three.js, Leaflet.js, Transformers.js\n";
echo "  backend: PHP (no framework), PDO/MySQL\n";
echo "  ml: Browser-based semantic search (all-MiniLM-L6-v2), hybrid regex + embeddings\n";
echo "  deployment: FTP sync to Namecheap shared hosting\n";
echo "  pwa: Service workers, Web App Manifest\n\n";

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
