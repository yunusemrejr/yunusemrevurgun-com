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
$nPosts = $countOr((new Blog())->getTotalPublishedPosts());
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
    'A new form of intelligence is emerging. — Yunus Emre Vurgun (Yemre), software developer and IT specialist.',
    [
        '<meta name="author" content="Yunus Emre Vurgun">',
        '<meta name="keywords" content="Yunus Emre Vurgun, Yemre, YEV, yunusemrejr, Yemrevu, software developer, developer, IT specialist, computational intelligence, operational technology">',
        '<meta property="og:description" content="A new form of intelligence is emerging. They say you\'re the average of the five people you spend the most time with. I\'m carefully curating mine.">',
        '<meta name="twitter:description" content="A new form of intelligence is emerging. They say you\'re the average of the five people you spend the most time with. I\'m carefully curating mine.">',
        '<script type="application/ld+json">' . json_encode($homeSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>',
    ]
);
?>
<body class="ui-landing">
<main>
<!-- vgpu WebGPU hero (bundled from dev/scripts/vgpu-hero/hero-entry.js).
     Falls back to plain palette background + HTML text when WebGPU is
     unavailable; prefers-reduced-motion renders one static frame. -->
<div class="ui-hero">
    <canvas class="ui-hero-canvas" aria-hidden="true"></canvas>
    <div class="ui-hero-content">
        <h1 class="ui-hero-title">Yemre</h1>
        <p class="ui-hero-tagline" data-tagline>The <span class="ui-tagline-word" data-tagline-noun>world</span> of <span class="ui-tagline-word" data-tagline-subject>a developer</span></p>
        <p class="ui-hero-lede">They say you’re the average of the five people you spend the most time with. I’m carefully curating mine.</p>
        <div class="ui-hero-actions">
            <a class="ui-landing-btn ui-landing-btn-primary" href="<?= FULL_BASE_PATH ?>about">About me</a>
            <a class="ui-landing-btn ui-landing-btn-secondary" href="<?= FULL_BASE_PATH ?>portfolio">See my work</a>
        </div>
    </div>
</div>

<!-- Landing brief: the hero carries the brand voice, this carries the facts.
     Below the fold, so the visual identity is untouched; it exists so a first
     visit can answer "who is this and what has he built" without clicking, and
     so the most-linked URL on the site has crawlable substance. -->
<div class="ui-landing-brief">
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
</div>
</main>

<!-- Cube-bot: decorative corner patroller (see assets/js/cube-bot.js) -->
<div class="cube-bot" aria-hidden="true">
    <div class="cube-bot-inner">
        <svg width="52" height="52" viewBox="0 0 56 52" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- hover feet -->
            <g class="cube-bot-feet">
                <rect class="cube-bot-foot cube-bot-foot-l" x="16" y="44" width="9" height="4" rx="2" fill="#8490a4"/>
                <rect class="cube-bot-foot cube-bot-foot-r" x="31" y="44" width="9" height="4" rx="2" fill="#8490a4"/>
            </g>
            <!-- cube body -->
            <rect x="8" y="12" width="40" height="34" rx="7" fill="#e3e2de" stroke="#8490a4" stroke-width="1.5"/>
            <!-- face panel -->
            <rect x="14" y="19" width="28" height="16" rx="4" fill="#8490a4" opacity="0.12"/>
            <!-- eyes -->
            <circle class="cube-bot-eye cube-bot-eye-l" cx="22" cy="26" r="2.6" fill="#434343"/>
            <circle class="cube-bot-eye cube-bot-eye-r" cx="34" cy="26" r="2.6" fill="#434343"/>
            <circle cx="22.9" cy="25.2" r="0.8" fill="#e3e2de"/>
            <circle cx="34.9" cy="25.2" r="0.8" fill="#e3e2de"/>
            <!-- mouth slit -->
            <rect x="24" y="31" width="8" height="1.6" rx="0.8" fill="#8fa6a6"/>
            <!-- side rivets -->
            <circle cx="11" cy="30" r="1" fill="#8490a4"/>
            <circle cx="45" cy="30" r="1" fill="#8490a4"/>
            <!-- two antennas -->
            <g class="cube-bot-antennas">
                <line class="cube-bot-ant-line cube-bot-ant-line-l" x1="17" y1="12" x2="13" y2="4" stroke="#8490a4" stroke-width="1.4"/>
                <circle class="cube-bot-ant cube-bot-ant-l" cx="13" cy="3.5" r="2.2" fill="#8fa6a6" stroke="#8490a4" stroke-width="1"/>
                <line class="cube-bot-ant-line cube-bot-ant-line-r" x1="39" y1="12" x2="43" y2="4" stroke="#8490a4" stroke-width="1.2"/>
                <circle class="cube-bot-ant cube-bot-ant-r" cx="43" cy="3" r="2.2" fill="#8fa6a6" stroke="#8490a4" stroke-width="1"/>
            </g>
        </svg>
    </div>
</div>

<footer class="ui-landing-footer">
    <div class="ui-landing-footer-inner">
        <nav class="ui-landing-footer-nav" aria-label="Footer navigation">
            <ul role="list">
                <?php foreach (ui_nav_items() as $item): ?>
                <li><a href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?= FULL_BASE_PATH ?>more">+</a></li>
            </ul>
        </nav>
        <p class="ui-landing-footer-copy">© <?= date('Y') ?> Yemre. All rights reserved.</p>
    </div>
</footer>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<script src="<?= FULL_BASE_PATH ?>assets/js/ui-interactions.js?v=<?= filemtime(__DIR__ . '/../assets/js/ui-interactions.js') ?>"></script>
<script src="<?= FULL_BASE_PATH ?>assets/js/vgpu-hero.min.js?v=<?= filemtime(__DIR__ . '/../assets/js/vgpu-hero.min.js') ?>"></script>
<script src="<?= FULL_BASE_PATH ?>assets/js/cube-bot.js?v=<?= filemtime(__DIR__ . '/../assets/js/cube-bot.js') ?>"></script>
<script>
(function () {
    var canvas = document.querySelector('.ui-hero-canvas');
    if (canvas && window.VgpuHero && typeof window.VgpuHero.mountVgpuHero === 'function') {
        window.VgpuHero.mountVgpuHero(canvas).catch(function () { /* CSS fallback stays */ });
    }
})();
</script>
</body>
</html>