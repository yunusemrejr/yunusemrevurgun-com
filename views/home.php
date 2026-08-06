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
            'alternateName' => 'Yemre',
            'url' => rtrim(FULL_BASE_PATH, '/') . '/',
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
    'Yemre | The world of a developer',
    'A new form of intelligence is emerging. — Yemre, the world of a developer.',
    [
        '<meta name="author" content="Yemre">',
        '<meta name="keywords" content="Yemre, developer, software, agents, intelligence, Yunus Emre Vurgun">',
        '<meta property="og:description" content="A new form of intelligence is emerging. They say you\'re the average of the five people you spend the most time with. I\'m carefully curating mine.">',
        '<meta name="twitter:description" content="A new form of intelligence is emerging. They say you\'re the average of the five people you spend the most time with. I\'m carefully curating mine.">',
        '<script type="application/ld+json">' . json_encode($homeSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>',
    ]
);
?>
<body class="ui-landing">
<?php
// Rotating SVG hero variants (desktop set + matching mobile set). One random
// variant renders on first paint; ui-interactions.js auto-rotates the rest.
$heroFiles = [
    ['desktop.svg', 'mobile.svg'],
    ['desktop2.svg', 'mobile2.svg'],
    ['desktop3.svg', 'mobile3.svg'],
    ['desktop4.svg', 'mobile4.svg'],
];
$heroIdx = array_rand($heroFiles);
$heroDesktopSrc = [];
$heroMobileSrc = [];
foreach ($heroFiles as $heroPair) {
    $heroDesktopSrc[] = FULL_BASE_PATH . 'assets/images/' . $heroPair[0] . '?v=' . filemtime(__DIR__ . '/../assets/images/' . $heroPair[0]);
    $heroMobileSrc[] = FULL_BASE_PATH . 'assets/images/' . $heroPair[1] . '?v=' . filemtime(__DIR__ . '/../assets/images/' . $heroPair[1]);
}
?>
<div class="ui-landing-svg" data-hero-current="<?= $heroIdx ?>" data-hero-desktop="<?= htmlspecialchars(implode('|', $heroDesktopSrc), ENT_QUOTES) ?>" data-hero-mobile="<?= htmlspecialchars(implode('|', $heroMobileSrc), ENT_QUOTES) ?>">
    <!-- Desktop SVG hero (navigation, portrait, background, headings, decorative elements) -->
    <img class="ui-landing-svg-desktop" src="<?= $heroDesktopSrc[$heroIdx] ?>" alt="Yemre — The world of a developer" loading="eager" draggable="false">
    <!-- Mobile SVG hero -->
    <img class="ui-landing-svg-mobile" src="<?= $heroMobileSrc[$heroIdx] ?>" alt="Yemre — The world of a developer" loading="eager" draggable="false">
</div>

<div class="ui-landing-actions">
    <a class="ui-landing-btn ui-landing-btn-primary" href="<?= FULL_BASE_PATH ?>about">About me</a>
    <a class="ui-landing-btn ui-landing-btn-secondary" href="<?= FULL_BASE_PATH ?>portfolio">See my work</a>
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
</body>
</html>