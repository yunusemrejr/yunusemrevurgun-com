<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Blog.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/seo.php';

if (!isset($GLOBALS['current_post'])) {
    header('Location: ' . FULL_BASE_PATH . 'blog');
    exit;
}

$post = $GLOBALS['current_post'];

$title = $post['title'] ?? 'Post';
$slug = $post['slug'] ?? '';
$plainText = seo_plain((string)($post['content'] ?? ''));
$wordCount = str_word_count($plainText);
$readingTime = max(1, (int)ceil($wordCount / 200));
$description = !empty($post['meta_description']) ? $post['meta_description'] : seo_description($plainText, 155);
$publishedDate = date('c', strtotime($post['created_at'] ?? 'now'));
$modifiedDate = date('c', strtotime($post['updated_at'] ?? $post['created_at'] ?? 'now'));
$postUrl = rtrim(FULL_BASE_PATH, '/') . '/blog/' . rawurlencode($slug);
$ogImage = !empty($post['featured_image'])
    ? seo_asset_url((string)$post['featured_image'])
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
            <article>
                <?php if (!empty($post['featured_image'])): ?>
                    <figure class="ui-figure" style="margin-bottom: var(--space-6);">
                        <img src="<?= htmlspecialchars(seo_asset_url((string)$post['featured_image'])) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
                    </figure>
                <?php endif; ?>
                <div class="ui-rich-content"><?= ui_sanitize_html($post['content'] ?? '') ?></div>
            </article>
            <nav class="ui-share-bar" aria-label="Post actions">
                <a class="ui-share-back" href="<?= FULL_BASE_PATH ?>blog"><span aria-hidden="true">←</span> Back to Blog</a>
                <div class="ui-share-keys" role="group" aria-labelledby="shareLabel">
                    <span class="ui-share-label" id="shareLabel">Share</span>
                    <a class="ui-share-key" href="https://twitter.com/intent/tweet?text=<?= rawurlencode($title) ?>&url=<?= rawurlencode($postUrl) ?>" target="_blank" rel="noopener noreferrer"><?= Socials::iconSvg('x', 'ui-share-icon') ?><span>Share on X</span></a>
                    <a class="ui-share-key" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= rawurlencode($postUrl) ?>" target="_blank" rel="noopener noreferrer"><?= Socials::iconSvg('linkedin', 'ui-share-icon') ?><span>LinkedIn</span></a>
                    <button class="ui-share-key" type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($postUrl, ENT_QUOTES) ?>');this.lastElementChild.textContent='Copied';this.classList.add('is-done');"><?= Socials::iconSvg('link', 'ui-share-icon') ?><span>Copy link</span></button>
                </div>
            </nav>
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
        <section class="ui-section">
            <?php ui_render_ebook_aside('article'); ?>
        </section>

        <?php if ($morePosts !== []): ?>
        <section class="ui-section">
            <h2 class="ui-section-title">Keep reading</h2>
            <ul class="ui-reading-list ui-reading-list--cards" role="list">
                <?php foreach ($morePosts as $more):
                    $moreText = seo_plain((string)($more['content'] ?? ''));
                    $moreExcerpt = trim((string)($more['excerpt'] ?? '')) !== '' ? trim(strip_tags((string)$more['excerpt'])) : $moreText;
                    $moreExcerpt = mb_strlen($moreExcerpt) > 150 ? rtrim(mb_substr($moreExcerpt, 0, 150)) . '…' : $moreExcerpt;
                    $moreMinutes = max(1, (int)ceil(str_word_count($moreText) / 200));
                ?>
                <li><a href="<?= FULL_BASE_PATH ?>blog/<?= rawurlencode($more['slug']) ?>">
                    <span class="ui-reading-main">
                        <span class="ui-reading-title"><?= htmlspecialchars($more['title']) ?></span>
                        <?php if ($moreExcerpt !== ''): ?><span class="ui-reading-excerpt"><?= htmlspecialchars($moreExcerpt) ?></span><?php endif; ?>
                    </span>
                    <span class="ui-reading-side">
                        <time class="ui-reading-date" datetime="<?= date('c', strtotime($more['created_at'] ?? 'now')) ?>"><?= date('M j, Y', strtotime($more['created_at'] ?? 'now')) ?></time>
                        <span class="ui-reading-time"><?= $moreMinutes ?> min read</span>
                    </span>
                </a></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <section class="ui-section">
            <div class="ui-author-card">
                <span class="ui-avatar-bezel ui-avatar-bezel--lg"><img src="<?= FULL_BASE_PATH ?>assets/images/pfp-avatar.webp" width="192" height="192" alt="" loading="lazy" decoding="async"></span>
                <div class="ui-author-body">
                    <p class="ui-author-kicker">Written by</p>
                    <h2 class="ui-author-name">Yunus Emre Vurgun</h2>
                    <p class="ui-author-role">Software developer and IT specialist in Istanbul, working on AI/ML systems, operational technology and industrial automation at ASP Otomasyon A.Ş.</p>
                    <div class="ui-author-links">
                        <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>about">About &amp; CV</a>
                        <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>portfolio">Project archive</a>
                        <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>contact">Contact</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
