<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';
require_once dirname(__DIR__) . '/models/RichText.php';

if (!isset($GLOBALS['current_update'])) {
    header('Location: ' . FULL_BASE_PATH . 'updates');
    exit;
}

$update = $GLOBALS['current_update'];

$title = ($update['title'] ?? 'Update') . ' — Yunus Emre Vurgun';
$plainText = RichText::plainText($update['description'] ?? '');
$description = mb_substr($plainText, 0, 160);
$updateUrl = rtrim(FULL_BASE_PATH, '/') . '/updates/' . (int) ($update['id'] ?? 0);
$published = date('c', strtotime($update['update_date'] ?? $update['created_at'] ?? 'now'));
$modified = date('c', strtotime($update['updated_at'] ?? $update['created_at'] ?? 'now'));
// Stable social-share image (social platforms don't render the site's SVGs).
$ogImage = FULL_BASE_PATH . 'assets/images/og-image.png';
$category = (string) ($update['category'] ?? '');
$importance = (string) ($update['importance'] ?? '');

$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => (string) ($update['title'] ?? 'Update'),
    'description' => (string) mb_substr($plainText, 0, 220),
    'datePublished' => $published,
    'dateModified' => $modified,
    'inLanguage' => 'en',
    'author' => ['@type' => 'Person', 'name' => 'Yunus Emre Vurgun', 'url' => rtrim(FULL_BASE_PATH, '/') . '/about'],
    'publisher' => ['@type' => 'Person', 'name' => 'Yunus Emre Vurgun', 'url' => rtrim(FULL_BASE_PATH, '/') . '/about'],
    'image' => $ogImage,
    'mainEntityOfPage' => $updateUrl,
    'url' => $updateUrl,
];
if ($category !== '') {
    $schema['articleSection'] = $category;
    $schema['keywords'] = array_values(array_filter([$category, $importance]));
}

$pageMeta = [
    // Badge styles shared with the /updates listing page.
    '<link rel="stylesheet" href="' . FULL_BASE_PATH . 'assets/css/updates.css?v=' . filemtime(dirname(__DIR__) . '/assets/css/updates.css') . '">',
    '<meta name="author" content="Yunus Emre Vurgun">',
    // Article metadata for social platforms. Default og/twitter tags (title,
    // description, image) are already emitted by ui_render_head with these
    // same values, so only the article-specific tags are added here.
    '<meta property="article:published_time" content="' . htmlspecialchars($published, ENT_QUOTES) . '">',
    '<meta property="article:modified_time" content="' . htmlspecialchars($modified, ENT_QUOTES) . '">',
    '<meta property="article:author" content="' . rtrim(FULL_BASE_PATH, '/') . '/about">',
    '<meta property="article:section" content="' . htmlspecialchars($category !== '' ? $category : 'Updates', ENT_QUOTES) . '">',
    '<meta property="article:tag" content="' . htmlspecialchars($importance !== '' ? ucfirst($importance) : 'Update', ENT_QUOTES) . '">',
    '<meta name="twitter:label1" content="Category">',
    '<meta name="twitter:data1" content="' . htmlspecialchars($category !== '' ? $category : 'Updates', ENT_QUOTES) . '">',
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
            <div class="ui-update-row" style="margin-bottom: 1.5rem;">
                <img
                    class="ui-update-avatar"
                    src="<?= FULL_BASE_PATH ?>assets/images/favicon.svg"
                    alt="Yunus Emre Vurgun"
                    loading="eager"
                >
                <div class="ui-update-body">
                    <p class="ui-eyebrow" style="margin-bottom: 0.25rem;">Updates</p>
                    <h1 class="ui-section-title" style="margin-bottom: 0.25rem;"><?= htmlspecialchars($update['title']) ?></h1>
                    <p class="ui-section-text">
                        <time datetime="<?= date('c', strtotime($update['update_date'] ?? $update['created_at'] ?? 'now')) ?>"><?= date('F j, Y H:i', strtotime($update['update_date'] ?? $update['created_at'] ?? 'now')) ?></time>
                    </p>
                </div>
            </div>
        </section>

        <section class="ui-section">
            <article class="ui-glass-panel">
                <?php if (!empty($update['category']) || !empty($update['importance'])): ?>
                    <div class="ui-tags" style="margin-bottom: 1rem;">
                        <?php if (!empty($update['category'])): ?><span class="ui-tag"><?= htmlspecialchars($update['category']) ?></span><?php endif; ?>
                        <?php if (!empty($update['importance'])): ?><span class="ui-importance is-<?= htmlspecialchars($update['importance']) ?>"><?= htmlspecialchars(ucfirst($update['importance'])) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="ui-rich-content"><?= RichText::markdown($update['description'] ?? '') ?></div>
            </article>
            <div class="ui-tags" style="margin-top: 1.5rem;">
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>updates">← Back to Updates</a>
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>updates.xml">RSS</a>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
