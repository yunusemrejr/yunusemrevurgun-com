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
// Each count links to the section it counts and agrees in number ("1 project").
$archiveLink = static fn(int $n, string $one, string $many, string $path): string =>
    '<a href="' . FULL_BASE_PATH . $path . '">' . $n . ' ' . ($n === 1 ? $one : $many) . '</a>';
$archiveParts = array_filter([
    $nProjects !== null ? $archiveLink($nProjects, 'project', 'projects', 'portfolio') : null,
    $nPosts !== null ? $archiveLink($nPosts, 'journal entry', 'journal entries', 'blog') : null,
    $nUpdates !== null ? $archiveLink($nUpdates, 'dated update', 'dated updates', 'updates') : null,
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

// The masthead mascot is the largest element on first paint. It renders 246 px wide
// on phones and 409 px on desktop, so one 640 px file covers up to about 2.6x pixel
// density on a phone and 1.5x on desktop (72 KB, down from 115 KB). It is a single
// file, not a srcset, so the Early Hints preload (which cannot carry a srcset) always
// names the file the <img> will use.
$mascotUrl = FULL_BASE_PATH . 'assets/images/mascot-640.webp?v=' . (@filemtime(dirname(__DIR__) . '/assets/images/mascot-640.webp') ?: 1);

ui_render_head(
    'Yunus Emre Vurgun (Yemre) — Software Developer & IT Specialist',
    'Yunus Emre Vurgun (Yemre), software developer and IT specialist in Istanbul. Explore projects, writing, and work in AI/ML and operational technology.',
    [
        'lcp_image' => ['src' => $mascotUrl],
        'hero' => true,
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
    <!-- Ambient field: a few signal packets travelling along the drafting grid's major rows (transform
         only, five tiny layers, paused off screen, absent under reduced motion). Decoration. -->
    <div class="ui-masthead-field" aria-hidden="true">
        <i style="--row:1;--dur:23s;--delay:-6s"></i>
        <i style="--row:2;--dur:31s;--delay:-19s"></i>
        <i style="--row:3;--dur:19s;--delay:-2s"></i>
        <i style="--row:4;--dur:27s;--delay:-11s"></i>
        <i style="--row:5;--dur:35s;--delay:-24s"></i>
    </div>
    <div class="ui-masthead-grid">
        <div class="ui-masthead-copy">
            <p class="ui-masthead-eyebrow">Istanbul · Operational technology &amp; AI</p>
            <h1 class="ui-masthead-title">Yunus Emre Vurgun</h1>
            <p class="ui-masthead-role">Software developer &amp; IT specialist</p>
            <p class="ui-masthead-lede">AI/ML systems, industrial automation, and the internal tools a factory floor depends on — documented as it is built.</p>
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
                    <dd class="ui-masthead-fact-value"><?= $archiveLine ?? 'Projects, journal, and notes' ?></dd>
                </div>
            </dl>
        </div>
        <?php require __DIR__ . '/includes/hero-stage.php'; ?>
        <!-- Three grid items in DOM order copy, figure, links: the copy holds nothing focusable, so Tab follows
             the picture on every screen (the figure sits first on phones and to the right on wide screens). -->
        <nav class="ui-masthead-links" aria-label="Start here">
            <a href="<?= FULL_BASE_PATH ?>about">About &amp; CV <span class="ui-arrow" aria-hidden="true"></span></a>
            <a href="<?= FULL_BASE_PATH ?>portfolio">Selected work <span class="ui-arrow" aria-hidden="true"></span></a>
            <a href="<?= FULL_BASE_PATH ?>contact">Contact <span class="ui-arrow" aria-hidden="true"></span></a>
        </nav>
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
            <div class="ui-landing-study-figures" data-deck>
                <figure data-deck-card>
                    <img src="<?= FULL_BASE_PATH ?>assets/images/landing-hero-desk.webp" width="1100" height="1100" decoding="async" loading="lazy"
                         alt="Yemre in a hoodie working at a vintage CRT computer, surrounded by machine-learning and C++ books under a desk lamp.">
                    <figcaption>The study: a vintage CRT, machine-learning and C++ books, and a desk lamp.</figcaption>
                </figure>
                <figure data-deck-card>
                    <img src="<?= ui_portrait_url() ?>" width="1000" height="1250" decoding="async" loading="lazy"
                         alt="Pencil portrait of Yunus Emre Vurgun.">
                    <figcaption>Yunus Emre Vurgun — Istanbul.</figcaption>
                </figure>
            </div>
        </div>
        <?php ui_render_figure([
            'file' => 'engine.svg',
            'width' => 640,
            'height' => 310,
            'alt' => 'Illustration of the Analytical Engine as a bench model: a card reader with a chain of punched cards, a Store of eight columns of digit wheels, a Mill of three columns of larger wheels, a toothed rack along the front, and a second card reader at the right. The card being read has a red edge.',
            'fig' => 'Fig. 1',
            'title' => 'Analytical Engine',
            'hint' => 'Illustration',
            'note' => 'Card: being read',
            'caption' => 'Babbage’s Analytical Engine, the first design for a general-purpose computer: punched cards choose the operation, a Store of digit wheels holds the numbers and a Mill does the arithmetic. The wheels are set to the opening values of Ada Lovelace’s Note G (V1 = 1, V2 = 2, V3 = n = 4); the red-edged card is the one being read.',
            'class' => 'fig--split',
        ]); ?>
    </section>

    <section class="ui-section">
        <h2 class="ui-brief-title">Inside this site</h2>
        <div class="ui-more-grid">
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>portfolio">
                <span class="ui-more-icon" aria-hidden="true"><?= ui_icon('archive') ?></span>
                <h3 class="ui-more-title">Project archive</h3>
                <p class="ui-more-desc">Systems across AI/ML, operational technology, industrial automation and web infrastructure — with the stack each one runs on.</p>
            </a>
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>blog">
                <span class="ui-more-icon" aria-hidden="true"><?= ui_icon('journal') ?></span>
                <h3 class="ui-more-title">Journal</h3>
                <p class="ui-more-desc">Long-form notes on AI capability trends, model releases, free internet access, and post-code engineering.</p>
            </a>
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>yunobot">
                <span class="ui-more-icon" aria-hidden="true"><?= ui_icon('bot') ?></span>
                <h3 class="ui-more-title">YunoBot</h3>
                <p class="ui-more-desc">An assistant that answers questions about this site entirely in your browser — no server call, no API key.</p>
            </a>
            <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>about">
                <span class="ui-more-icon" aria-hidden="true"><?= ui_icon('profile') ?></span>
                <h3 class="ui-more-title">About &amp; CV</h3>
                <p class="ui-more-desc">Education, work history at ASP Otomasyon A.Ş., certifications, and where to reach me.</p>
            </a>
        </div>
    </section>
    <section class="ui-section">
        <h2 class="ui-brief-title">Projects you can run</h2>
        <div class="ui-more-grid">
            <a class="ui-more-card ui-more-card--plain" href="<?= FULL_BASE_PATH ?>chessko">
                <h3 class="ui-more-title">Chessko</h3>
                <p class="ui-more-desc">Chess against Stockfish 19 compiled to WebAssembly, or a home-grown engine that wobbles on purpose. Seven levels, no sign-up.</p>
            </a>
            <a class="ui-more-card ui-more-card--plain" href="<?= FULL_BASE_PATH ?>gemmaclaim">
                <h3 class="ui-more-title">Claim Splitter</h3>
                <p class="ui-more-desc">Paste a claim and a 270M-parameter model, running in your browser, lists its premises and hidden assumptions.</p>
            </a>
            <a class="ui-more-card ui-more-card--plain" href="<?= FULL_BASE_PATH ?>jelloshop">
                <h3 class="ui-more-title">Jello Shop</h3>
                <p class="ui-more-desc">A calm coffee-shop game with a cat whose behaviour comes from a 1,194-weight neural network.</p>
            </a>
            <a class="ui-more-card ui-more-card--plain" href="<?= FULL_BASE_PATH ?>downloads">
                <h3 class="ui-more-title">Open-source downloads</h3>
                <p class="ui-more-desc">Seven Linux games, tools and coding agents, each with its requirements, how to run it and a GitHub link.</p>
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

<?php ui_render_footer('landing-right'); ?>
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/hero.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/hero.js') ?>" defer></script>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
</body>
</html>