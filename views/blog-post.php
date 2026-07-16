<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

if (!isset($GLOBALS['current_post'])) {
    header('Location: ' . FULL_BASE_PATH . 'blog');
    exit;
}

$post = $GLOBALS['current_post'];

$title = $post['title'] ?? 'Post';
$slug = $post['slug'] ?? '';
$plainText = strip_tags((string)($post['content'] ?? ''));
$wordCount = str_word_count($plainText);
$readingTime = max(1, (int)ceil($wordCount / 200));
$description = !empty($post['meta_description']) ? $post['meta_description'] : mb_substr($plainText, 0, 220);
$publishedDate = date('c', strtotime($post['created_at'] ?? 'now'));
$modifiedDate = date('c', strtotime($post['updated_at'] ?? $post['created_at'] ?? 'now'));
$postUrl = rtrim(FULL_BASE_PATH, '/') . '/blog/' . rawurlencode($slug);
$ogImage = !empty($post['featured_image'])
    ? FULL_BASE_PATH . ltrim(htmlspecialchars($post['featured_image']), '/')
    : FULL_BASE_PATH . 'assets/images/yunus-emre-vurgun-portrait.jpg';

$extraMeta = [
    '<meta property="og:type" content="article">',
    '<meta property="og:url" content="' . htmlspecialchars($postUrl) . '">',
    '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '">',
    '<meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES) . '">',
    '<meta property="og:image" content="' . htmlspecialchars($ogImage) . '">',
    '<meta property="og:image:alt" content="' . htmlspecialchars($title, ENT_QUOTES) . '">',
    '<meta property="og:site_name" content="Yµn ^…^ ƒ(x) Personal Website">',
    '<meta property="article:published_time" content="' . $publishedDate . '">',
    '<meta property="article:modified_time" content="' . $modifiedDate . '">',
    '<meta property="article:author" content="' . FULL_BASE_PATH . 'about">',
    '<meta property="article:section" content="Technology">',
    '<meta property="article:tag" content="software development">',
    '<meta name="twitter:card" content="summary_large_image">',
    '<meta name="twitter:url" content="' . htmlspecialchars($postUrl) . '">',
    '<meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '">',
    '<meta name="twitter:description" content="' . htmlspecialchars($description, ENT_QUOTES) . '">',
    '<meta name="twitter:image" content="' . htmlspecialchars($ogImage) . '">',
    '<meta name="twitter:label1" content="Reading time">',
    '<meta name="twitter:data1" content="' . $readingTime . ' min read">',
    '<meta name="twitter:label2" content="Author">',
    '<meta name="twitter:data2" content="Yunus Emre Vurgun">',
    '<meta name="author" content="Yunus Emre Vurgun">',
    '<meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">',
    '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $postUrl,
        ],
        'headline' => $title,
        'description' => $description,
        'image' => [
            '@type' => 'ImageObject',
            'url' => $ogImage,
        ],
        'datePublished' => $publishedDate,
        'dateModified' => $modifiedDate,
        'author' => [
            '@type' => 'Person',
            'name' => 'Yunus Emre Vurgun',
            'url' => FULL_BASE_PATH . 'about',
            'sameAs' => [
                'https://github.com/yunusemrejr',
                'https://linkedin.com/in/yunus-emre-vurgun-49ba9a177',
                'https://x.com/yemrevu',
                'https://instagram.com/yemrevu',
            ],
        ],
        'publisher' => [
            '@type' => 'Person',
            'name' => 'Yunus Emre Vurgun',
            'url' => FULL_BASE_PATH . 'about',
        ],
        'wordCount' => $wordCount,
        'timeRequired' => 'PT' . $readingTime . 'M',
        'articleBody' => mb_substr($plainText, 0, 2000),
        'inLanguage' => 'en-US',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>',
];

ui_render_head(
    $title . ' | Journal',
    $description,
    $extraMeta
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Journal'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Journal</p>
            <h1 class="ui-section-title"><?= htmlspecialchars($post['title']) ?></h1>
            <p class="ui-section-text">Published <?= date('F j, Y', strtotime($post['created_at'] ?? 'now')) ?></p>
        </section>

        <section class="ui-section">
            <article class="ui-glass-panel" itemscope itemtype="https://schema.org/BlogPosting">
                <?php if (!empty($post['featured_image'])): ?>
                    <img class="ui-media" src="<?= FULL_BASE_PATH . htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
                <?php endif; ?>
                <div class="ui-rich-content"><?= ui_sanitize_html($post['content'] ?? '') ?></div>
            </article>
            <div class="ui-tags" style="margin-top: 1.5rem;">
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>blog">← Back to Blog</a>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
</body>
</html>
