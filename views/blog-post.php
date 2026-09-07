<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Blog.php';
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
    ? FULL_BASE_PATH . ltrim($post['featured_image'], '/')
    : FULL_BASE_PATH . 'assets/images/og-image.png';

$extraMeta = [
    'canonical' => $postUrl,
    '<meta property="og:type" content="article">',
    '<meta property="og:url" content="' . htmlspecialchars($postUrl) . '">',
    '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '">',
    '<meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES) . '">',
    'og_image' => $ogImage,
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
            'sameAs' => array_values(array_filter(array_map(
                fn($s) => !empty($s['url']) ? $s['url'] : null,
                (new Socials())->getActiveLinks()
            ))),
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
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>',
];

ui_render_head(
    $title . ' | Yemre',
    $description,
    $extraMeta
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Journal'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Journal</p>
            <h1 class="ui-section-title"><?= htmlspecialchars($post['title']) ?></h1>
            <p class="ui-section-text"><a href="<?= FULL_BASE_PATH ?>about">Yunus Emre Vurgun</a> · <?= $readingTime ?> min read<br>Published <?= date('F j, Y', strtotime($post['created_at'] ?? 'now')) ?><?= $modifiedDate !== $publishedDate ? ' · updated ' . date('F j, Y', strtotime($post['updated_at'])) : '' ?></p>
        </section>

        <section class="ui-section">
            <article class="ui-glass-panel">
                <?php if (!empty($post['featured_image'])): ?>
                    <img class="ui-media" src="<?= FULL_BASE_PATH . htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
                <?php endif; ?>
                <div class="ui-rich-content"><?= ui_sanitize_html($post['content'] ?? '') ?></div>
            </article>
            <div class="ui-tags" style="margin-top: 1.5rem;">
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>blog">← Back to Blog</a>
                <a class="ui-btn ui-btn-secondary" href="https://twitter.com/intent/tweet?text=<?= rawurlencode($title) ?>&url=<?= rawurlencode($postUrl) ?>" target="_blank" rel="noopener noreferrer">Share on X</a>
                <a class="ui-btn ui-btn-secondary" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= rawurlencode($postUrl) ?>" target="_blank" rel="noopener noreferrer">Share on LinkedIn</a>
                <button class="ui-btn ui-btn-secondary" type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($postUrl, ENT_QUOTES) ?>');this.textContent='Copied';">Copy link</button>
            </div>
        </section>

        <?php
        // A finished post is the highest-intent moment on the site, and it used
        // to end at "Back to Blog". Recent writing keeps the reader here; the
        // three routes say who wrote it.
        $blog = new Blog();
        $morePosts = array_values(array_filter(
            $blog->getPosts(0, 5),
            static fn(array $p): bool => ($p['slug'] ?? '') !== $slug && ($p['slug'] ?? '') !== ''
        ));
        ?>
        <?php if ($morePosts !== []): ?>
        <section class="ui-section">
            <p class="ui-eyebrow">Continue</p>
            <h2 class="ui-section-title">Keep reading</h2>
            <div class="ui-grid" style="margin-top: 1.5rem;">
                <?php foreach ($morePosts as $more): ?>
                <a class="ui-link-card" href="<?= FULL_BASE_PATH ?>blog/<?= rawurlencode($more['slug']) ?>">
                    <h3 class="ui-card-title"><?= htmlspecialchars($more['title']) ?></h3>
                    <p class="ui-card-meta"><?= date('F j, Y', strtotime($more['created_at'] ?? 'now')) ?></p>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="ui-section">
            <p class="ui-eyebrow">The author</p>
            <h2 class="ui-section-title">Yunus Emre Vurgun</h2>
            <p class="ui-section-text">Software developer and IT specialist in Istanbul, working on AI/ML systems, operational technology and industrial automation at ASP Otomasyon A.Ş.</p>
            <div class="ui-tags">
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>about">About &amp; CV</a>
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>portfolio">Project archive</a>
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>contact">Contact</a>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
