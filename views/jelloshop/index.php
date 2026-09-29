<?php
/**
 * /jelloshop: the mascot's coffee shop. A small browser game — walk the jell-o
 * around the room, pick up coffee beans — with the page text that explains it,
 * so crawlers get a real page here and not just a canvas.
 */
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/models/Socials.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';

$base = FULL_BASE_PATH;
$origin = rtrim(FULL_BASE_PATH, '/');
$pageUrl = $origin . '/jelloshop';
$basePath = rtrim((string)parse_url(FULL_BASE_PATH, PHP_URL_PATH), '/');
$assetsUrl = $basePath . '/assets/jelloshop';
$appJs = dirname(__DIR__, 2) . '/assets/jelloshop/js/jelloshop.js';
$appCss = dirname(__DIR__, 2) . '/assets/css/jelloshop.css';
$modified = date('c', max(filemtime(__FILE__), (int)@filemtime($appJs), (int)@filemtime($appCss)));

$title = 'Jello Shop: Walk Your Jelly Mascot Around a Coffee Shop in Your Browser';
$description = 'A small, calm browser game: walk the jelly mascot around a hand-drawn coffee shop, collect coffee beans, and listen to the shop music. Runs entirely in your browser, on desktop and mobile.';

$faq = [
    ['What is Jello Shop?', 'It is a tiny room. A hand-drawn coffee shop, drawn in the site\'s colours, with the jelly mascot walking around inside it. You tell it where to go by clicking the floor.'],
    ['How do I move the jell-o?', 'Click or tap anywhere on the floor and the jell-o waddles over. On a desktop, move the mouse and it turns to look at you. Arrow keys work too.'],
    ['What are the coffee beans for?', 'Walking over one picks it up and adds a bean to the counter in the corner. That is the whole game. There is nothing to win and nothing to spend them on.'],
    ['Is the shop open?', 'Not really. The door has a hand-drawn CLOSED sign on it and there is a rope across it, because the jell-o only gets one room.'],
    ['Does it need to download anything?', 'No. The room, the jell-o and the beans are drawn with canvas, and the music and the walking sound are synthesised in the browser. There are no images or audio files to fetch.'],
    ['Does it work on a phone?', 'Yes. The room keeps its proportions and scales down to fit the screen, so on a small screen you get the whole shop at once, just smaller.'],
    ['Is my score stored anywhere?', 'No. The bean count lives in the page while it is open and is gone when you leave. Nothing is sent to a server.'],
];

$schemaGraph = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebApplication',
            '@id' => $pageUrl . '#app',
            'name' => 'Jello Shop',
            'url' => $pageUrl,
            'description' => $description,
            'applicationCategory' => 'GameApplication',
            'genre' => 'Casual walking game',
            'operatingSystem' => 'Any (modern web browser)',
            'browserRequirements' => 'Requires JavaScript and the Canvas 2D API',
            'isAccessibleForFree' => true,
            'inLanguage' => 'en',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'featureList' => [
                'Click or tap to walk the jell-o around the room',
                'Coffee beans to collect and a bean counter',
                'Hand-drawn coffee shop in a 2.5D room',
                'Background music and walking sounds synthesised in the browser',
                'Scales to fit a phone screen',
            ],
            'author' => ['@type' => 'Person', 'name' => 'Yunus Emre Vurgun', 'url' => $origin . '/about'],
            'dateModified' => $modified,
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn(array $q): array => [
                '@type' => 'Question',
                'name' => $q[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]],
            ], $faq),
        ],
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $origin . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'More', 'item' => $origin . '/more'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => 'Jello Shop', 'item' => $pageUrl],
            ],
        ],
    ],
];

ui_render_head($title, $description, [
    'jelloshop' => 'game',
    'og_image' => FULL_BASE_PATH . 'assets/images/og-chessko.png?v=' . (@filemtime(dirname(__DIR__, 2) . '/assets/images/og-chessko.png') ?: 1),
    '<meta property="og:type" content="website">',
    '<meta name="keywords" content="jelly game, browser game, coffee shop game, walk around game, club penguin style, casual browser game, mascot game">',
    '<script type="application/ld+json">' . json_encode($schemaGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>',
]);
?>
<body class="ui-jelloshop-page">
<div class="ui-page">
    <?php ui_render_navbar('more'); ?>

    <main class="ui-main js-page" id="main-content" tabindex="-1">
        <nav class="js-crumbs" aria-label="Breadcrumb">
            <ol>
                <li><a href="<?= $base ?>">Home</a></li>
                <li><a href="<?= $base ?>more">More</a></li>
                <li aria-current="page">Jello Shop</li>
            </ol>
        </nav>

        <header class="js-head">
            <h1 class="js-title">Jello Shop: a small coffee shop for the mascot</h1>
            <p class="js-lead">One room, one jell-o, and coffee beans scattered on the floor. Click the floor and it waddles over; move the mouse and it turns to look at you. It runs entirely in your browser — no account, no download, nothing to win.</p>
        </header>

        <section class="js-stage" aria-label="Jello Shop">
            <div class="js-canvas-wrap">
                <canvas id="js-canvas" width="960" height="600" role="img"
                        aria-label="A hand-drawn coffee shop. The jelly mascot walks around the room and picks up coffee beans."></canvas>

                <p class="js-hud" aria-hidden="true">
                    <svg class="js-bean-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <ellipse cx="12" cy="12" rx="8.5" ry="10.5" fill="#8a5a33"/>
                        <ellipse cx="12" cy="12" rx="8.5" ry="10.5" fill="none" stroke="#3e2412" stroke-width="1.6"/>
                        <path d="M12 2.2c3 3.4-3 5.4 0 8.8s-3 5.4 0 8.8" fill="none" stroke="#2e1a0c" stroke-width="1.8" stroke-linecap="round"/>
                        <ellipse cx="9" cy="7.5" rx="2" ry="3.4" fill="#ffe1c8" opacity="0.35" transform="rotate(-20 9 7.5)"/>
                    </svg>
                    <span id="js-beans">0</span>
                </p>
                <p class="visually-hidden" id="js-beans-live" role="status" aria-live="polite">0 coffee beans</p>

                <button type="button" class="js-sound" id="js-sound" aria-pressed="false">
                    <svg class="js-sound-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M4 9.5h3.5L12 5.5v13L7.5 14.5H4z" fill="currentColor"/>
                        <path d="M15.5 9a4.2 4.2 0 0 1 0 6" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                        <path d="M18 6.6a7.6 7.6 0 0 1 0 10.8" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                    </svg>
                    <span class="js-sound-label">Sound off</span>
                </button>

                <div class="js-start">
                    <button type="button" class="js-start-card" id="js-start">
                        <p class="js-start-title">Welcome to Jello Shop</p>
                        <p class="js-start-text">The door is closed and the jell-o has the whole room to itself.</p>
                        <p class="js-start-hint">Click the floor to start walking</p>
                    </button>
                </div>
            </div>
            <noscript><p class="js-noscript">Jello Shop is a game that runs in your browser, so it needs JavaScript. The room, the jell-o and the coffee beans are all drawn in the page.</p></noscript>
        </section>

        <section class="js-section" aria-labelledby="js-how">
            <h2 id="js-how">How the shop works</h2>
            <p>Click or tap anywhere on the floor and the jell-o waddles over to that spot. While it is standing still it turns to face your cursor, so moving the mouse across the room makes it turn around. The arrow keys work as well, which is handy on a laptop with a trackpad.</p>
            <p>Coffee beans appear on the floor from time to time. Walk the jell-o over one and it is picked up and counted in the corner. There is no scoring system, no unlocks and nothing to spend the beans on — it is a small quiet thing to click around in.</p>
            <p>The shop is deliberately a single room. The door on the far wall has a hand-drawn <em>CLOSED</em> sign, a note about the floor being swept, and a rope across it.</p>
        </section>

        <section class="js-section" aria-labelledby="js-build">
            <h2 id="js-build">How it is drawn</h2>
            <p>The room is a one-point-perspective box drawn with canvas paths: a back wall, a floor that widens towards you, and a dado rail around the walls. The jell-o walks in a handful of depth lanes, exactly the way a Club Penguin penguin walked across a room in 2006, and furniture is sorted back to front so it can pass behind the counter and in front of the couches.</p>
            <p>Nothing is an image. The jell-o, the beans and every piece of furniture are canvas paths in the site's own palette, the sketchy ink lines are a fixed random wobble so the room does not shimmer, and the music and the little walking sound are synthesised with the Web Audio API. That is why the whole shop is one script and no downloads.</p>
        </section>

        <section class="js-section" aria-labelledby="js-faq">
            <h2 id="js-faq">Jello Shop FAQ</h2>
            <dl>
                <?php foreach ($faq as [$q, $a]): ?>
                <div style="margin-bottom:var(--space-4)">
                    <dt style="font-weight:600;margin-bottom:var(--space-2)"><?= htmlspecialchars($q) ?></dt>
                    <dd style="margin:0;line-height:1.65;color:var(--color-text-secondary)"><?= htmlspecialchars($a) ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
        </section>

        <section class="js-section" aria-labelledby="js-credits">
            <h2 id="js-credits">Credits</h2>
            <ul class="js-credits">
                <li>The room, the jell-o, the beans and the audio are written for this page. No images, no audio files, no libraries, no tracking.</li>
                <li>The mascot is the same red jelly in the navy and cream cap that appears elsewhere on this site.</li>
                <li>Type is Fredoka and Atkinson Hyperlegible Next, served from this site.</li>
            </ul>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<script type="module" src="<?= $assetsUrl ?>/js/jelloshop.js?v=<?= @filemtime($appJs) ?: 1 ?>"></script>
<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
</body>
</html>
