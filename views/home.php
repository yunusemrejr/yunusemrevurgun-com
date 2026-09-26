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
            'image' => ui_portrait_url(),
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
<div class="ui-page">
    <?php ui_render_navbar('home'); ?>

<main class="ui-landing-main" id="main-content" tabindex="-1">
<!-- Front page of the record: name, current identity, facts, and actions.
     The most-linked URL answers "who is this" without scrolling or clicking. -->
<header class="ui-masthead">
    <div class="ui-masthead-grid">
        <div>
            <p class="ui-masthead-eyebrow">Istanbul · Operational technology &amp; AI</p>
            <h1 class="ui-masthead-title">Yunus Emre Vurgun</h1>
            <p class="ui-masthead-role">Software developer &amp; IT specialist</p>
            <p class="ui-masthead-lede">AI/ML systems, industrial automation, and the internal tools a factory floor depends on — documented as it is built, at ASP&nbsp;Otomasyon&nbsp;A.Ş.</p>
            <dl class="ui-masthead-facts">
                <div class="ui-masthead-fact">
                    <dt class="ui-masthead-fact-term">Currently</dt>
                    <dd class="ui-masthead-fact-value">Software Developer &amp; IT Specialist, ASP&nbsp;Otomasyon&nbsp;A.Ş.</dd>
                </div>
                <div class="ui-masthead-fact">
                    <dt class="ui-masthead-fact-term">Focus</dt>
                    <dd class="ui-masthead-fact-value">AI/ML systems · Industrial automation · Internal tools</dd>
                </div>
                <div class="ui-masthead-fact">
                    <dt class="ui-masthead-fact-term">This site</dt>
                    <dd class="ui-masthead-fact-value"><?= $archiveLine !== null ? htmlspecialchars(ucfirst($archiveLine)) : 'Projects, journal, and notes' ?></dd>
                </div>
            </dl>
            <nav class="ui-masthead-links" aria-label="Start here">
                <a href="<?= FULL_BASE_PATH ?>about">About &amp; CV →</a>
                <a href="<?= FULL_BASE_PATH ?>portfolio">Selected work →</a>
                <a href="<?= FULL_BASE_PATH ?>contact">Contact →</a>
            </nav>
        </div>
        <figure class="ui-masthead-figure">
            <img src="<?= ui_portrait_url() ?>" width="1000" height="1250" decoding="async" fetchpriority="high"
                 alt="Pencil portrait of Yunus Emre Vurgun.">
            <figcaption>Yunus Emre Vurgun — Istanbul.</figcaption>
        </figure>
    </div>
</header>

<div class="ui-landing-brief">
    <section class="ui-section">
        <h2 class="ui-brief-title">Systems that have to keep running.</h2>
        <div class="ui-landing-study">
            <div>
                <p class="ui-section-text">They say you’re the average of the five people you spend the most time with. I’m carefully curating mine — and the systems I build: architecturally robust, computationally efficient, grounded in time-resistant fundamentals.</p>
                <p class="ui-section-text">From CPU-optimized neural network experiments to industrial automation, the work is documented here as it happens.</p>
                <a class="ui-more-link" href="<?= FULL_BASE_PATH ?>about">Full profile, education, and work history →</a>
            </div>
            <figure>
                <img src="<?= FULL_BASE_PATH ?>assets/images/landing-hero-desk.webp" width="1100" height="1100" decoding="async" loading="lazy"
                     alt="Yemre in a hoodie working at a vintage CRT computer, surrounded by machine-learning and C++ books under a desk lamp.">
                <figcaption>The study: a vintage CRT, machine-learning and C++ books, and a desk lamp.</figcaption>
            </figure>
        </div>
    </section>

    <section class="ui-section">
        <h2 class="ui-brief-title">Inside this site</h2>
        <div class="ui-more-grid">
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>portfolio">
                <span class="ui-more-icon" aria-hidden="true">01</span>
                <h3 class="ui-more-title">Project archive</h3>
                <p class="ui-more-desc">Systems across AI/ML, operational technology, industrial automation and web infrastructure — with the stack each one runs on.</p>
            </a>
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>blog">
                <span class="ui-more-icon" aria-hidden="true">02</span>
                <h3 class="ui-more-title">Journal</h3>
                <p class="ui-more-desc">Long-form notes on AI capability trends, model releases, free internet access, and post-code engineering.</p>
            </a>
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>yunobot">
                <span class="ui-more-icon" aria-hidden="true">03</span>
                <h3 class="ui-more-title">YunoBot</h3>
                <p class="ui-more-desc">An assistant that answers questions about this site entirely in your browser — no server call, no API key.</p>
            </a>
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>about">
                <span class="ui-more-icon" aria-hidden="true">04</span>
                <h3 class="ui-more-title">About &amp; CV</h3>
                <p class="ui-more-desc">Education, work history at ASP Otomasyon A.Ş., certifications, and where to reach me.</p>
            </a>
        </div>
    </section>
    <?php ui_render_ebook_feature(); ?>
    <?php if ($latestWriting): ?>
    <section class="ui-section">
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
        <a class="ui-more-link" href="<?= FULL_BASE_PATH ?>blog">All writing →</a>
    </section>
    <?php endif; ?>
</div>
</main>
</div>

<footer class="ui-landing-footer">
    <div class="ui-landing-footer-inner">
        <nav class="ui-landing-footer-nav" aria-label="Footer navigation">
            <ul role="list">
                <?php foreach (ui_nav_items() as $item): ?>
                <li><a href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?= FULL_BASE_PATH ?>more">More</a></li>
                <li><a href="<?= EBOOK_URL ?>" target="_blank" rel="noopener noreferrer">Book ↗</a></li>
            </ul>
        </nav>
        <p class="ui-landing-footer-copy">© <?= date('Y') ?> Yemre. All rights reserved.</p>
        <?php ui_render_hampton('landing-right'); ?>
    </div>
</footer>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<script src="<?= FULL_BASE_PATH ?>assets/js/navigation.js?v=<?= filemtime(__DIR__ . '/../assets/js/navigation.js') ?>"></script>
<script src="<?= FULL_BASE_PATH ?>assets/js/ui-interactions.js?v=<?= filemtime(__DIR__ . '/../assets/js/ui-interactions.js') ?>"></script>
</body>
</html>