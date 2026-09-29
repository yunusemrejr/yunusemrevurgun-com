<?php
/**
 * /chessko/<slug>: one explainer article, from views/chessko/articles.php.
 * Emits TechArticle + BreadcrumbList (+ FAQPage when the article has questions) structured data.
 */
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/models/Socials.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';

$articles = require __DIR__ . '/articles.php';
$base = FULL_BASE_PATH;
$origin = rtrim(FULL_BASE_PATH, '/');

$isDev = php_sapi_name() === 'cli-server' || getenv('MODE') === 'development';
$path = $isDev
    ? trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/')
    : trim((string)($_GET['url'] ?? ''), '/');
$slug = str_starts_with($path, 'chessko/') ? substr($path, strlen('chessko/')) : '';

if (!isset($articles[$slug])) {
    http_response_code(404);
    require dirname(__DIR__) . '/404.php';
    return;
}

$article = $articles[$slug];
$render = static fn(string $html): string => str_replace('{{base}}', $base, $html);
$url = $origin . '/chessko/' . $slug;

$plain = strip_tags($article['lead'] . ' ' . implode(' ', array_map(static fn(array $s): string => $s[1], $article['sections'])));
$words = str_word_count(html_entity_decode($plain));
$minutes = max(1, (int)ceil($words / 220));

$toc = [];
foreach ($article['sections'] as $i => [$heading]) {
    $toc[] = ['id' => 's' . ($i + 1) . '-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($heading)), '-'), 'label' => $heading];
}

$graph = [
    [
        '@type' => 'TechArticle',
        '@id' => $url . '#article',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'headline' => $article['h1'],
        'description' => $article['description'],
        'datePublished' => $article['published'],
        'dateModified' => $article['modified'],
        'wordCount' => $words,
        'timeRequired' => 'PT' . $minutes . 'M',
        'inLanguage' => 'en',
        'articleSection' => 'Chess engines',
        'about' => ['@type' => 'Thing', 'name' => 'Chess engine'],
        'isPartOf' => ['@type' => 'WebApplication', 'name' => 'Chessko', 'url' => $origin . '/chessko'],
        'author' => ['@type' => 'Person', 'name' => 'Yunus Emre Vurgun', 'url' => $origin . '/about'],
        'publisher' => ['@type' => 'Person', 'name' => 'Yunus Emre Vurgun', 'url' => $origin . '/about'],
    ],
    [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $origin . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'More', 'item' => $origin . '/more'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Chessko', 'item' => $origin . '/chessko'],
            ['@type' => 'ListItem', 'position' => 4, 'name' => $article['title'], 'item' => $url],
        ],
    ],
];
if (!empty($article['faq'])) {
    $graph[] = [
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static fn(array $q): array => [
            '@type' => 'Question',
            'name' => $q[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]],
        ], $article['faq']),
    ];
}

ui_render_head($article['title'], $article['description'], [
    'chessko' => true,
    'og_image' => FULL_BASE_PATH . 'assets/chessko/images/og.png?v=' . (@filemtime(dirname(__DIR__, 2) . '/assets/chessko/images/og.png') ?: 1),
    'og_type' => 'article',
    '<meta property="og:type" content="article">',
    '<meta property="article:published_time" content="' . $article['published'] . '">',
    '<meta property="article:modified_time" content="' . $article['modified'] . '">',
    '<meta property="article:author" content="' . $origin . '/about">',
    '<meta property="article:section" content="Chess engines">',
    '<meta name="twitter:label1" content="Reading time">',
    '<meta name="twitter:data1" content="' . $minutes . ' min read">',
    '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>',
]);
$others = array_diff_key($articles, [$slug => true]);
?>
<body class="ui-chessko-page">
<div class="ui-page">
    <?php ui_render_navbar('more'); ?>

    <main class="ui-main ck-page" id="main-content" tabindex="-1">
        <nav class="ck-crumbs" aria-label="Breadcrumb">
            <ol>
                <li><a href="<?= $base ?>">Home</a></li>
                <li><a href="<?= $base ?>more">More</a></li>
                <li><a href="<?= $base ?>chessko">Chessko</a></li>
                <li aria-current="page"><?= htmlspecialchars($article['title']) ?></li>
            </ol>
        </nav>

        <article class="ck-article">
            <header class="ck-head">
                <p class="ui-eyebrow"><?= htmlspecialchars($article['eyebrow']) ?></p>
                <h1 class="ck-title"><?= htmlspecialchars($article['h1']) ?></h1>
                <p class="ck-byline"><a href="<?= $base ?>about">Yunus Emre Vurgun</a> · <?= $minutes ?> min read · <time datetime="<?= $article['published'] ?>"><?= date('F j, Y', strtotime($article['published'])) ?></time><?= $article['modified'] !== $article['published'] ? ' · updated <time datetime="' . $article['modified'] . '">' . date('F j, Y', strtotime($article['modified'])) . '</time>' : '' ?></p>
                <p class="ck-lead"><?= htmlspecialchars($article['lead']) ?></p>
            </header>

            <?php if (count($toc) > 3): ?>
            <nav class="ck-toc" aria-label="In this article">
                <p class="ck-toc-title">In this article</p>
                <ol>
                    <?php foreach ($toc as $t): ?>
                    <li><a href="#<?= $t['id'] ?>"><?= htmlspecialchars($t['label']) ?></a></li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php endif; ?>

            <div class="ui-rich-content ck-body">
                <?php foreach ($article['sections'] as $i => [$heading, $html]): ?>
                <section aria-labelledby="<?= $toc[$i]['id'] ?>">
                    <h2 id="<?= $toc[$i]['id'] ?>"><?= htmlspecialchars($heading) ?></h2>
                    <?= $render($html) ?>
                </section>
                <?php endforeach; ?>

                <?php if (!empty($article['faq'])): ?>
                <section aria-labelledby="faq">
                    <h2 id="faq">Quick answers</h2>
                    <dl class="ck-faq">
                        <?php foreach ($article['faq'] as [$q, $a]): ?>
                        <div><dt><?= htmlspecialchars($q) ?></dt><dd><?= htmlspecialchars($a) ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                </section>
                <?php endif; ?>
            </div>

            <aside class="ck-cta">
                <p>Try what you just read about.</p>
                <a class="ck-button" href="<?= $base ?>chessko">Play Chessko</a>
            </aside>
        </article>

        <section class="ck-section" aria-labelledby="ck-more">
            <h2 id="ck-more">More about Chessko</h2>
            <ul class="ck-articles" role="list">
                <?php foreach ($others as $s => $a): ?>
                <li>
                    <a href="<?= $base ?>chessko/<?= htmlspecialchars($s) ?>">
                        <span class="ck-articles-title"><?= htmlspecialchars($a['h1']) ?></span>
                        <span class="ck-articles-desc"><?= htmlspecialchars($a['description']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
</body>
</html>
