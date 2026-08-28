<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Settings.php';
require_once dirname(__DIR__) . '/models/Socials.php';
require_once __DIR__ . '/includes/ui.php';

$settings = new Settings();

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
<!-- vgpu WebGPU hero (bundled from dev/scripts/vgpu-hero/hero-entry.js).
     Falls back to plain palette background + HTML text when WebGPU is
     unavailable; prefers-reduced-motion renders one static frame. -->
<div class="ui-hero">
    <canvas class="ui-hero-canvas" aria-hidden="true"></canvas>
    <div class="ui-hero-content">
        <h1 class="ui-hero-title">Yemre</h1>
        <p class="ui-hero-tagline">The world of a developer</p>
        <p class="ui-hero-lede">They say you’re the average of the five people you spend the most time with. I’m carefully curating mine.</p>
        <div class="ui-hero-actions">
            <a class="ui-landing-btn ui-landing-btn-primary" href="<?= FULL_BASE_PATH ?>about">About me</a>
            <a class="ui-landing-btn ui-landing-btn-secondary" href="<?= FULL_BASE_PATH ?>portfolio">See my work</a>
        </div>
    </div>
</div>

<!-- Duck-bot: decorative corner patroller (see assets/js/duck-bot.js) -->
<div class="duck-bot" aria-hidden="true">
    <div class="duck-bot-inner">
        <svg width="56" height="48" viewBox="0 0 56 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- legs -->
            <g class="duck-bot-legs">
                <rect class="duck-bot-leg duck-bot-leg-l" x="22" y="38" width="3" height="9" rx="1.5" fill="#8490a4"/>
                <rect class="duck-bot-leg duck-bot-leg-r" x="31" y="38" width="3" height="9" rx="1.5" fill="#8490a4"/>
                <rect x="19.5" y="45" width="8" height="2.5" rx="1.25" fill="#8490a4"/>
                <rect x="28.5" y="45" width="8" height="2.5" rx="1.25" fill="#8490a4"/>
            </g>
            <!-- body -->
            <ellipse cx="28" cy="28" rx="17" ry="12" fill="#e3e2de" stroke="#8490a4" stroke-width="1.5"/>
            <!-- panel seam + rivets -->
            <path d="M13 28c4-3 9-4.5 15-4.5S39 25 43 28" stroke="#8490a4" stroke-width="1" opacity="0.5"/>
            <circle cx="18" cy="31" r="1" fill="#8490a4"/>
            <circle cx="38" cy="31" r="1" fill="#8490a4"/>
            <!-- tail -->
            <path d="M11 26c-3-1-5-1-7 1 2 2 4 2 6 1.5" fill="#e3e2de" stroke="#8490a4" stroke-width="1.2"/>
            <!-- head -->
            <circle cx="40" cy="15" r="8.5" fill="#e3e2de" stroke="#8490a4" stroke-width="1.5"/>
            <!-- neck -->
            <rect x="35" y="20" width="9" height="8" fill="#e3e2de"/>
            <!-- eye -->
            <circle class="duck-bot-eye" cx="42.5" cy="13.5" r="2.2" fill="#575757"/>
            <circle cx="43.2" cy="12.8" r="0.7" fill="#e3e2de"/>
            <!-- beak -->
            <path class="duck-bot-beak" d="M47.5 13.5h7l-3 3.5h-4z" fill="#8fa6a6" stroke="#8490a4" stroke-width="0.8"/>
            <!-- antenna -->
            <line x1="40" y1="6.5" x2="40" y2="3.5" stroke="#8490a4" stroke-width="1.2"/>
            <circle class="duck-bot-antenna" cx="40" cy="3" r="2" fill="#8fa6a6" stroke="#8490a4" stroke-width="1"/>
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
<script src="<?= FULL_BASE_PATH ?>assets/js/duck-bot.js?v=<?= filemtime(__DIR__ . '/../assets/js/duck-bot.js') ?>"></script>
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