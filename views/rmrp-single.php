<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';
require_once dirname(__DIR__) . '/models/RichText.php';
require_once dirname(__DIR__) . '/models/Rmrp.php';
require_once __DIR__ . '/includes/visuals.php';

if (!isset($GLOBALS['current_rmrp'])) {
    header('Location: ' . FULL_BASE_PATH . 'rmrp');
    exit;
}

$memory = $GLOBALS['current_rmrp'];

$title = ($memory['title'] ?? 'Memory') . ' — Yunus Emre Vurgun';
$plainText = RichText::plainText($memory['description'] ?? '');
$description = mb_substr($plainText, 0, 160);
$memoryUrl = rtrim(FULL_BASE_PATH, '/') . '/rmrp/' . (int) ($memory['id'] ?? 0);
$published = date('c', strtotime($memory['memory_date'] ?? $memory['created_at'] ?? 'now'));
$modified = date('c', strtotime($memory['updated_at'] ?? $memory['created_at'] ?? 'now'));
// Stable social-share image (social platforms don't render the site's SVGs).
$ogImage = FULL_BASE_PATH . 'assets/images/og-image.png';
// Auto-added extras: topic, cover art, figures, neighbours and related reads.
$mTopic = vis_classify(($memory['title'] ?? '') . ' ' . $plainText);
$figures = vis_key_numbers($plainText, 4);
$minutes = vis_reading_minutes($plainText);
$words = str_word_count($plainText);
$takeaway = vis_first_sentence($plainText);
$showTakeaway = mb_strlen($plainText) > mb_strlen($takeaway) + 40
    && mb_stripos($takeaway, (string) ($memory['title'] ?? '')) !== 0;
$all = [];
foreach ((new Rmrp())->getAllMemories() as $row) {
    $row['_plain'] = RichText::plainText($row['description'] ?? '');
    $row['_topic'] = vis_classify(($row['title'] ?? '') . ' ' . $row['_plain']);
    $all[] = $row;
}
$pos = null;
foreach ($all as $i => $row) { if ((int) $row['id'] === (int) $memory['id']) { $pos = $i; break; } }
$newer = ($pos !== null && $pos > 0) ? $all[$pos - 1] : null;
$older = ($pos !== null && $pos < count($all) - 1) ? $all[$pos + 1] : null;
$related = array_slice(array_values(array_filter($all, fn($r) => (int) $r['id'] !== (int) $memory['id'] && $r['_topic'] === $mTopic)), 0, 3);
$randomIds = implode(',', array_map(static fn($r) => (int) $r['id'], $all));
$dateRaw = $memory['memory_date'] ?? $memory['created_at'] ?? 'now';
$category = (string) ($memory['category'] ?? '');
$importance = (string) ($memory['importance'] ?? '');

$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => (string) ($memory['title'] ?? 'Memory'),
    'description' => (string) mb_substr($plainText, 0, 220),
    'datePublished' => $published,
    'dateModified' => $modified,
    'inLanguage' => 'en',
    // Author is the site owner (required for Article rich results); no
    // author/pfp UI on the page itself, unlike the Updates log.
    'author' => ['@type' => 'Person', 'name' => 'Yunus Emre Vurgun', 'url' => rtrim(FULL_BASE_PATH, '/') . '/about'],
    'publisher' => ['@type' => 'Person', 'name' => 'Yunus Emre Vurgun', 'url' => rtrim(FULL_BASE_PATH, '/') . '/about'],
    'image' => $ogImage,
    'mainEntityOfPage' => $memoryUrl,
    'url' => $memoryUrl,
];
if ($category !== '') {
    $schema['articleSection'] = $category;
    $schema['keywords'] = array_values(array_filter([$category, $importance]));
}

$pageMeta = [
    '<link rel="stylesheet" href="' . FULL_BASE_PATH . 'assets/css/visuals.css?v=' . filemtime(dirname(__DIR__) . '/assets/css/visuals.css') . '">',
    '<script src="' . FULL_BASE_PATH . 'assets/js/rmrp-extras.js?v=' . filemtime(dirname(__DIR__) . '/assets/js/rmrp-extras.js') . '" defer></script>',
    // Badge styles shared with the /rmrp listing page.
    '<link rel="stylesheet" href="' . FULL_BASE_PATH . 'assets/css/rmrp.css?v=' . filemtime(dirname(__DIR__) . '/assets/css/rmrp.css') . '">',
    // Article metadata for social platforms. Default og/twitter tags (title,
    // description, image) are already emitted by ui_render_head with these
    // same values, so only the article-specific tags are added here.
    '<meta property="article:published_time" content="' . htmlspecialchars($published, ENT_QUOTES) . '">',
    '<meta property="article:modified_time" content="' . htmlspecialchars($modified, ENT_QUOTES) . '">',
    '<meta property="article:author" content="' . rtrim(FULL_BASE_PATH, '/') . '/about">',
    '<meta property="article:section" content="' . htmlspecialchars($category !== '' ? $category : 'Memories', ENT_QUOTES) . '">',
    '<meta property="article:tag" content="' . htmlspecialchars($importance !== '' ? ucfirst($importance) : 'Memory', ENT_QUOTES) . '">',
    '<meta name="twitter:label1" content="Category">',
    '<meta name="twitter:data1" content="' . htmlspecialchars($category !== '' ? $category : 'Memories', ENT_QUOTES) . '">',
    '<meta name="twitter:label2" content="Importance">',
    '<meta name="twitter:data2" content="' . htmlspecialchars($importance !== '' ? ucfirst($importance) : 'General', ENT_QUOTES) . '">',
    '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>',
];

ui_render_head($title, $description, $pageMeta);
?>
<body class="ui-rmrp-page ui-rmrp-single">
<div class="ui-rmrp-progress" aria-hidden="true"><span></span></div>
<div class="ui-page">
    <?php ui_render_navbar('Journal'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <article class="ui-rmrp-article">
            <nav class="ui-rmrp-crumbs" aria-label="Breadcrumb">
                <a href="<?= FULL_BASE_PATH ?>rmrp">← All memories</a>
                <a href="<?= FULL_BASE_PATH ?>rmrp?topic=<?= htmlspecialchars($mTopic) ?>"><?= htmlspecialchars(vis_topic_label($mTopic)) ?></a>
            </nav>

            <header class="ui-rmrp-article-head">
                <div class="ui-rmrp-article-cover"><?= vis_cover($mTopic, (int) $memory['id']) ?></div>
                <p class="ui-rmrp-meta">
                    <span class="ui-rmrp-topic-pill is-inline"><?= htmlspecialchars(vis_topic_label($mTopic)) ?></span>
                    <time datetime="<?= $published ?>"><?= date('F j, Y', strtotime($dateRaw)) ?></time>
                    <span aria-hidden="true">·</span>
                    <span><?= $minutes ?> min read</span>
                    <span aria-hidden="true">·</span>
                    <span><?= $words ?> words</span>
                </p>
                <h1 class="ui-section-title"><?= htmlspecialchars($memory['title']) ?></h1>
                <?php if (!empty($memory['importance'])): ?>
                    <div class="ui-tags"><span class="ui-importance is-<?= htmlspecialchars($memory['importance']) ?>"><?= htmlspecialchars(ucfirst($memory['importance'])) ?> importance</span></div>
                <?php endif; ?>
            </header>

            <?php if ($showTakeaway): ?>
                <p class="ui-rmrp-takeaway"><span>The short version</span><?= htmlspecialchars($takeaway) ?></p>
            <?php endif; ?>

            <?php if ($figures): ?>
                <ul class="ui-rmrp-bignums" aria-label="Figures mentioned">
                    <?php foreach ($figures as $f): ?>
                        <li><strong><?= htmlspecialchars($f['value']) ?></strong><span><?= htmlspecialchars($f['unit']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="ui-rich-content ui-rmrp-prose"><?= RichText::markdown($memory['description'] ?? '') ?></div>

            <div class="ui-rmrp-actions">
                <button type="button" class="ui-btn ui-btn-secondary" data-copy-link>Copy link</button>
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>rmrp/<?= (int) $memory['id'] ?>" data-random-memory data-ids="<?= htmlspecialchars($randomIds) ?>" data-exclude="<?= (int) $memory['id'] ?>">Another memory</a>
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>rmrp.xml">RSS</a>
            </div>
        </article>

        <?php if ($newer || $older): ?>
            <nav class="ui-rmrp-neighbours" aria-label="Neighbouring memories">
                <?php foreach ([['Older', $older], ['Newer', $newer]] as [$label, $n]): if (!$n) { echo '<span></span>'; continue; } ?>
                    <a class="ui-rmrp-neighbour" href="<?= FULL_BASE_PATH ?>rmrp/<?= (int) $n['id'] ?>">
                        <span class="ui-rmrp-neighbour-art"><?= vis_cover($n['_topic'], (int) $n['id']) ?></span>
                        <span class="ui-rmrp-neighbour-text"><small><?= $label ?></small><strong><?= htmlspecialchars($n['title']) ?></strong></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php if ($related): ?>
            <section class="ui-section ui-rmrp-related">
                <h2 class="ui-brief-title">More on <?= htmlspecialchars(strtolower(vis_topic_label($mTopic))) ?></h2>
                <div class="ui-rmrp-grid is-grid">
                    <?php foreach ($related as $r): ?>
                        <article class="ui-rmrp-card">
                            <a class="ui-rmrp-cover" href="<?= FULL_BASE_PATH ?>rmrp/<?= (int) $r['id'] ?>" tabindex="-1" aria-hidden="true"><?= vis_cover($r['_topic'], (int) $r['id']) ?></a>
                            <div class="ui-rmrp-body">
                                <p class="ui-rmrp-meta"><time datetime="<?= date('c', strtotime($r['memory_date'] ?? 'now')) ?>"><?= date('M j, Y', strtotime($r['memory_date'] ?? 'now')) ?></time></p>
                                <h3 class="ui-feed-title"><a href="<?= FULL_BASE_PATH ?>rmrp/<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['title']) ?></a></h3>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
