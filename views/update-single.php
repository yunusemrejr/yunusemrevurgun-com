<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/seo.php';
require_once dirname(__DIR__) . '/models/RichText.php';

if (!isset($GLOBALS['current_update'])) {
    header('Location: ' . FULL_BASE_PATH . 'updates');
    exit;
}

$update = $GLOBALS['current_update'];

$title = ($update['title'] ?? 'Update') . ' — Yunus Emre Vurgun';
$plainText = RichText::plainText($update['description'] ?? '');
$description = seo_description($plainText, 155);
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
            <?php
            $updateStamp = strtotime($update['update_date'] ?? $update['created_at'] ?? 'now');
            // Entries dated without a time are stored at midnight; "00:00" would read as a real timestamp.
            $updateWhen = date('H:i', $updateStamp) === '00:00' ? date('F j, Y', $updateStamp) : date('F j, Y · H:i', $updateStamp);
            ?>
            <div class="ui-update-head">
                <span class="ui-avatar-bezel ui-avatar-bezel--lg">
                    <img
                        src="<?= FULL_BASE_PATH ?>assets/images/pfp-avatar.webp"
                        width="192" height="192"
                        alt="Yunus Emre Vurgun"
                        loading="eager"
                    >
                </span>
                <div class="ui-update-body">
                    <p class="ui-eyebrow">Update</p>
                    <h1 class="ui-section-title"><?= htmlspecialchars($update['title']) ?></h1>
                    <p class="ui-section-text">
                        <time datetime="<?= date('c', $updateStamp) ?>"><?= $updateWhen ?></time>
                    </p>
                </div>
            </div>
        </section>

        <section class="ui-section">
            <article>
                <?php if (!empty($update['category']) || !empty($update['importance'])): ?>
                    <div class="ui-tags ui-article-tags">
                        <?php if (!empty($update['category'])): ?><span class="ui-tag"><?= htmlspecialchars($update['category']) ?></span><?php endif; ?>
                        <?php if (!empty($update['importance'])): ?><span class="ui-importance is-<?= htmlspecialchars($update['importance']) ?>"><?= htmlspecialchars(ucfirst($update['importance'])) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="ui-rich-content"><?= RichText::markdown($update['description'] ?? '') ?></div>
            </article>
            <nav class="ui-share-bar" aria-label="Update actions">
                <a class="ui-share-back" href="<?= FULL_BASE_PATH ?>updates"><span aria-hidden="true">←</span> Back to Updates</a>
                <div class="ui-share-keys">
                    <a class="ui-share-key" href="<?= FULL_BASE_PATH ?>updates.xml"><svg class="ui-share-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5.5" cy="18.5" r="2.2" fill="currentColor"/><path d="M4 10.5a9.5 9.5 0 0 1 9.5 9.5M4 4a16 16 0 0 1 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg><span>RSS feed</span></a>
                </div>
            </nav>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
