<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Settings.php';
require_once dirname(__DIR__) . '/models/Socials.php';
require_once dirname(__DIR__) . '/models/Portfolio.php';
require_once dirname(__DIR__) . '/models/Blog.php';
require_once dirname(__DIR__) . '/models/Updates.php';
require_once __DIR__ . '/includes/ui.php';

$settings = new Settings();

// Proof of substance for the landing page: real counts, server-rendered.
// fetchColumn() returns a numeric string; (int) guards against a false on a
// missing table so the sentence never renders "0 projects" by accident.
$countOr = static function ($value): ?int {
    return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
};
$nProjects = $countOr((new Portfolio())->getTotalProjects());
$blog = new Blog();
$nPosts = $countOr($blog->getTotalPublishedPosts());
$latestWriting = $blog->getPosts(0, 3);
$nUpdates = $countOr((new Updates())->getTotalUpdates());
$archiveParts = array_filter([
    $nProjects !== null ? $nProjects . ' projects' : null,
    $nPosts !== null ? $nPosts . ' journal entries' : null,
    $nUpdates !== null ? $nUpdates . ' dated updates' : null,
]);
$archiveLine = $archiveParts !== [] ? implode(', ', $archiveParts) . '.' : null;

$homeSchema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebSite',
            '@id' => rtrim(FULL_BASE_PATH, '/') . '/#website',
            'url' => rtrim(FULL_BASE_PATH, '/') . '/',
            'name' => 'Yemre — The world of a developer',
            'description' => 'A new form of intelligence is emerging.',
            'inLanguage' => 'en-US',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => rtrim(FULL_BASE_PATH, '/') . '/search?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@type' => 'Person',
            '@id' => rtrim(FULL_BASE_PATH, '/') . '/#person',
            'name' => 'Yunus Emre Vurgun',
            'givenName' => 'Yunus Emre',
            'familyName' => 'Vurgun',
            'additionalName' => 'Yemre',
            'alternateName' => ['Yemre', 'YEV', 'yunusemrejr', 'Yemrevu'],
            'url' => rtrim(FULL_BASE_PATH, '/') . '/',
            'image' => FULL_BASE_PATH . 'assets/images/yunus-emre-vurgun-portrait.jpg',
            'description' => 'Software developer and IT specialist (Yemre, YEV, yunusemrejr) focusing on computational intelligence, AI/ML systems, operational technology, and industrial automation.',
            'knowsAbout' => [
                'software development',
                'AI/ML systems',
                'operational technology',
                'industrial automation',
                'full-stack web development',
                'computational intelligence',
            ],
            'sameAs' => array_values(array_filter(array_map(
                fn($s) => !empty($s['url']) ? $s['url'] : null,
                (new Socials())->getActiveLinks()
            ))),
            'jobTitle' => 'Software Developer & IT Specialist',
            'worksFor' => [
                '@type' => 'Organization',
                'name' => 'ASP Otomasyon A.Ş.',
            ],
        ],
    ],
];

ui_render_head(
    'Yunus Emre Vurgun (Yemre) — Software Developer & IT Specialist',
    'Yunus Emre Vurgun (Yemre), software developer and IT specialist in Istanbul. Explore projects, writing, and work in AI/ML and operational technology.',
    [
        '<meta name="author" content="Yunus Emre Vurgun">',
        '<meta name="keywords" content="Yunus Emre Vurgun, Yemre, YEV, yunusemrejr, Yemrevu, software developer, developer, IT specialist, computational intelligence, operational technology">',
        '<meta property="og:description" content="A new form of intelligence is emerging. They say you\'re the average of the five people you spend the most time with. I\'m carefully curating mine.">',
        '<meta name="twitter:description" content="A new form of intelligence is emerging. They say you\'re the average of the five people you spend the most time with. I\'m carefully curating mine.">',
        '<script type="application/ld+json">' . json_encode($homeSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>',
    ]
);
?>
<body class="ui-landing">
<a class="ui-skip-link" href="#home-archive">Skip to explore</a>
<main id="main-content" tabindex="-1">
<!-- Procedural WebGL study; content remains available without graphics. -->
<div class="ui-hero ui-hero--fallback">
    <canvas class="ui-hero-canvas" aria-hidden="true"></canvas>
    <button class="ui-scene-toggle" type="button" data-scene-toggle aria-pressed="false" hidden>Pause background</button>
    <div class="ui-hero-content">
        <h1 class="ui-hero-title">Yemre</h1>
        <figure class="ui-hero-frame">
            <a class="ui-hero-image-link" href="<?= FULL_BASE_PATH ?>assets/images/landing-hero-desk.webp" data-image-expand aria-label="Enlarge the study image" aria-haspopup="dialog">
            <img src="<?= FULL_BASE_PATH ?>assets/images/landing-hero-desk.webp" width="1100" height="1100" decoding="async" fetchpriority="high"
                 alt="Yemre in a hoodie working at a vintage CRT computer, surrounded by machine-learning and C++ books under a desk lamp.">
                <span class="ui-image-expand-hint" aria-hidden="true">View details ↗</span>
            </a>
        </figure>
        <p class="ui-hero-tagline">The world of a developer</p>
        <p class="ui-hero-lede">They say you’re the average of the five people you spend the most time with. I’m carefully curating mine.</p>
        <div class="ui-hero-actions">
            <a class="ui-landing-btn ui-landing-btn-primary" href="<?= FULL_BASE_PATH ?>about">About me</a>
            <a class="ui-landing-btn ui-landing-btn-secondary" href="<?= FULL_BASE_PATH ?>portfolio">See my work</a>
        </div>
        <a class="ui-landing-explore" href="#home-archive">Explore the archive ↓</a>
    </div>
</div>

<!-- Landing brief: the hero carries the brand voice, this carries the facts.
     Below the fold, so the visual identity is untouched; it exists so a first
     visit can answer "who is this and what has he built" without clicking, and
     so the most-linked URL on the site has crawlable substance. -->
<div class="ui-landing-brief" id="home-archive" tabindex="-1">
    <section class="ui-section">
        <p class="ui-eyebrow">Istanbul · Operational technology &amp; AI</p>
        <h2 class="ui-brief-title">Software developer and IT specialist building systems that have to keep running.</h2>
        <p class="ui-section-text">AI/ML systems, industrial automation, and the internal tools a factory floor depends on — documented as it is built.<?= $archiveLine !== null ? ' This archive holds ' . htmlspecialchars($archiveLine) : '' ?></p>
    </section>

    <section class="ui-section">
        <div class="ui-more-grid">
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>portfolio">
                <span class="ui-more-icon" aria-hidden="true">[]</span>
                <h3 class="ui-more-title">Project archive</h3>
                <p class="ui-more-desc">Systems across AI/ML, operational technology, industrial automation and web infrastructure — with the stack each one runs on.</p>
            </a>
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>blog">
                <span class="ui-more-icon" aria-hidden="true">¶</span>
                <h3 class="ui-more-title">Journal</h3>
                <p class="ui-more-desc">Long-form notes on AI capability trends, model releases, free internet access, and post-code engineering.</p>
            </a>
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>yunobot">
                <span class="ui-more-icon" aria-hidden="true">>_</span>
                <h3 class="ui-more-title">YunoBot</h3>
                <p class="ui-more-desc">An assistant that answers questions about this site entirely in your browser — no server call, no API key.</p>
            </a>
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>about">
                <span class="ui-more-icon" aria-hidden="true">@</span>
                <h3 class="ui-more-title">About &amp; CV</h3>
                <p class="ui-more-desc">Education, work history at ASP Otomasyon A.Ş., certifications, and where to reach me.</p>
            </a>
        </div>
    </section>
    <?php if ($latestWriting): ?>
    <section class="ui-section">
        <p class="ui-eyebrow">From the journal</p>
        <h2 class="ui-section-title">Latest writing</h2>
        <div class="ui-feed-list">
            <?php foreach ($latestWriting as $entry): ?>
            <article class="ui-feed-card">
                <p class="ui-feed-meta"><time datetime="<?= date('c', strtotime($entry['created_at'])) ?>"><?= date('F j, Y', strtotime($entry['created_at'])) ?></time></p>
                <h3 class="ui-feed-title"><a href="<?= FULL_BASE_PATH . 'blog/' . rawurlencode($entry['slug']) ?>"><?= htmlspecialchars($entry['title']) ?></a></h3>
                <?php if (!empty($entry['excerpt'])): ?><p class="ui-feed-text"><?= htmlspecialchars(mb_substr(strip_tags($entry['excerpt']), 0, 200)) ?></p><?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <a class="ui-landing-explore" href="<?= FULL_BASE_PATH ?>blog">All writing →</a>
    </section>
    <?php endif; ?>
</div>
</main>
<dialog class="ui-image-dialog" aria-labelledby="hero-image-title">
    <div class="ui-image-dialog-bar">
        <p id="hero-image-title">The study · a closer look</p>
        <button type="button" class="ui-image-dialog-close" autofocus>Close <span aria-hidden="true">×</span></button>
    </div>
    <div class="ui-image-dialog-scroll">
        <img src="<?= FULL_BASE_PATH ?>assets/images/landing-hero-desk.webp" width="1100" height="1100" loading="lazy" alt="Yemre at a vintage CRT computer, with programming and machine-learning books, cables, and a warm desk lamp.">
    </div>
    <div class="ui-image-dialog-tools">
        <button type="button" data-image-zoom aria-pressed="false">Original size</button>
        <a href="<?= FULL_BASE_PATH ?>assets/images/landing-hero-desk.webp" target="_blank" rel="noopener">Open image ↗</a>
    </div>
</dialog>

<footer class="ui-landing-footer">
    <div class="ui-landing-footer-inner">
        <nav class="ui-landing-footer-nav" aria-label="Footer navigation">
            <ul role="list">
                <?php foreach (ui_nav_items() as $item): ?>
                <li><a href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?= FULL_BASE_PATH ?>more">More</a></li>
            </ul>
        </nav>
        <p class="ui-landing-footer-copy">© <?= date('Y') ?> Yemre. All rights reserved.</p>
        <?php ui_render_hampton('landing-right'); ?>
    </div>
</footer>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<script src="<?= FULL_BASE_PATH ?>assets/js/ui-interactions.js?v=<?= filemtime(__DIR__ . '/../assets/js/ui-interactions.js') ?>"></script>
<script src="<?= FULL_BASE_PATH ?>assets/js/decorative-effects.js?v=<?= filemtime(__DIR__ . '/../assets/js/decorative-effects.js') ?>" data-effect-src="<?= FULL_BASE_PATH ?>assets/js/vgpu-hero.min.js?v=<?= filemtime(__DIR__ . '/../assets/js/vgpu-hero.min.js') ?>" data-effect-kind="hero"></script>
</body>
</html>