<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';
require_once dirname(__DIR__) . '/models/RichText.php';

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
<body>
<div class="ui-page">
    <?php ui_render_navbar('Journal'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow" style="margin-bottom: 0.25rem;">Random Memories</p>
            <h1 class="ui-section-title" style="margin-bottom: 0.25rem;"><?= htmlspecialchars($memory['title']) ?></h1>
            <p class="ui-section-text">
                <time datetime="<?= date('c', strtotime($memory['memory_date'] ?? $memory['created_at'] ?? 'now')) ?>"><?= date('F j, Y H:i', strtotime($memory['memory_date'] ?? $memory['created_at'] ?? 'now')) ?></time>
            </p>
        </section>

        <section class="ui-section">
            <article>
                <?php if (!empty($memory['category']) || !empty($memory['importance'])): ?>
                    <div class="ui-tags" style="margin-bottom: 1rem;">
                        <?php if (!empty($memory['category'])): ?><span class="ui-tag"><?= htmlspecialchars($memory['category']) ?></span><?php endif; ?>
                        <?php if (!empty($memory['importance'])): ?><span class="ui-importance is-<?= htmlspecialchars($memory['importance']) ?>"><?= htmlspecialchars(ucfirst($memory['importance'])) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="ui-rich-content"><?= RichText::markdown($memory['description'] ?? '') ?></div>
            </article>
            <div class="ui-doc-links">
                <a href="<?= FULL_BASE_PATH ?>rmrp">← Back to Memories</a>
                <a href="<?= FULL_BASE_PATH ?>rmrp.xml">RSS</a>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
