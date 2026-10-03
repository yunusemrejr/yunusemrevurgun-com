<?php
/**
 * /jelloshop: Jell-omo's coffee shop. A small browser game — walk the jelly
 * around the room, pick up coffee beans — with the page text that explains it,
 * so crawlers get a real page here and not just a canvas.
 */
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/models/Socials.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/views/includes/seo.php';

$base = FULL_BASE_PATH;
$origin = rtrim(FULL_BASE_PATH, '/');
$pageUrl = $origin . '/jelloshop';
$basePath = rtrim((string)parse_url(FULL_BASE_PATH, PHP_URL_PATH), '/');
$assetsUrl = $basePath . '/assets/jelloshop';
$jsDir = dirname(__DIR__, 2) . '/assets/jelloshop/js';
$appJs = $jsDir . '/jelloshop.js';
$modules = ['jello3d', 'audio', 'draw', 'room', 'props', 'catbrain', 'catweights'];
$importMap = ['imports' => []];
$jsNewest = (int)@filemtime($appJs);
foreach ($modules as $m) {
    $mt = (int)@filemtime($jsDir . '/' . $m . '.js');
    $jsNewest = max($jsNewest, $mt);
    $importMap['imports']['jelloshop/' . $m] = $assetsUrl . '/js/' . $m . '.js?v=' . ($mt ?: 1);
}
$appCss = dirname(__DIR__, 2) . '/assets/css/jelloshop.css';
$modified = date('c', max(filemtime(__FILE__), $jsNewest, (int)@filemtime($appCss)));

$title = 'Cozy Coffee Shop Browser Game – Jello Shop, Nothing to Install';
$description = 'A calm browser game: walk Jell-omo, a 3D jelly in a baseball cap, around a hand-drawn coffee shop, collect beans and pet a cat run by a tiny neural network.';

$faq = [
    ['What is Jello Shop?', 'It is a tiny cosy room: a hand-drawn coffee shop with a rainy window, a fireplace, a striped rug and a sleeping cat. Jell-omo, the jelly mascot of this site, lives in it, and you tell him where to go by clicking the floor.'],
    ['Who is Jell-omo?', 'The red jelly in the navy and cream baseball cap, the same mascot that sits in the corner of every page on this site. In the shop he is a real 3D model, so he turns to face the way he walks, squashes when he lands, wobbles when he stops, and shows you the back of his cap when he walks away.'],
    ['How do I move him?', 'Click or tap anywhere on the floor and Jell-omo waddles over. On a desktop, move the mouse and he turns to look at you. Arrow keys and WASD work too.'],
    ['What are the coffee beans for?', 'Walking over one picks it up and adds it to the counter in the corner, and each pickup chimes a note higher up a little scale. That is the whole game. There is nothing to win and nothing to spend them on, although the menu on the wall is priced in them.'],
    ['Can I pet the cat?', 'Click the cat and it wakes up, meows and sends up a heart. Walk Jell-omo close to it and it lifts its head to look at him.'],
    ['Does the cat do anything by itself?', 'Yes. It sleeps on its cushion most of the time, but every so often it wakes, sits up, grooms, wanders round the room and comes back to sleep. Nothing is scripted: a tiny neural network (a 12-unit recurrent GRU with about 1,200 weights, about 5 KB) chooses what to do twice a second, from how rested and restless the cat is, where Jell-omo is, and a little noise. It was trained offline with evolution strategies and runs in your browser, so it never plays the same day twice.'],
    ['Does the weather change?', 'The rain drifts between a drizzle and a downpour, and sometimes there is lightning at the window followed, after a delay that depends on how far off it was, by thunder. A close clap can wake the cat. If your device asks for reduced motion, the flashes are softened and never come in pairs.'],
    ['What does the coffee machine do?', 'The more beans you collect, the busier it gets: the steam over the machine grows thicker and rises faster, and it lets off a little hiss every few beans.'],
    ['Is the shop open?', 'Not really. The door has a hand-drawn CLOSED sign, because Jell-omo only gets one room.'],
    ['Does it need to download anything?', 'Only the code. The room is drawn with canvas, Jell-omo is rendered live by a WebGL shader, and the music, the rain and the sound effects are synthesised in the browser. There are no images or audio files to fetch. If your browser has no WebGL, Jell-omo falls back to the flat mascot picture.'],
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
            'browserRequirements' => 'Requires JavaScript and the Canvas 2D API; WebGL for the 3D avatar (falls back to a flat picture)',
            'isAccessibleForFree' => true,
            'inLanguage' => 'en',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'featureList' => [
                'Click or tap to walk Jell-omo, a live 3D jelly, around the room',
                'Coffee beans to collect and a bean counter',
                'Hand-drawn cosy coffee shop with a rainy window, lightning, a fireplace and a cat with a tiny neural network for a brain',
                'Lo-fi background music, rain and sound effects synthesised in the browser',
                'Scales to fit a phone screen',
            ],
            'author' => ['@type' => 'Person', 'name' => 'Yunus Emre Vurgun', 'url' => $origin . '/about'],
            'dateModified' => $modified,
            'mentions' => seo_things(['gru', 'evolution_strategy']),
            'subjectOf' => ['@type' => 'TechArticle', 'name' => 'How Jello Shop\'s cat decides what to do', 'url' => $origin . '/jelloshop/cat-brain'],
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
    '<meta name="keywords" content="jelly game, browser game, coffee shop game, cozy game, walk around game, club penguin style, casual browser game, mascot game, Jell-omo">',
    '<script type="importmap">' . json_encode($importMap, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>',
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
            <h1 class="js-title">Jello Shop: a cosy coffee shop for Jell-omo</h1>
            <p class="js-lead">One rainy evening, one fireplace, one sleepy cat, and coffee beans scattered on the rug. Click the floor and Jell-omo waddles over; move the mouse and he turns to look at you. It runs entirely in your browser — no account, nothing to win.</p>
        </header>

        <section class="js-stage" aria-label="Jello Shop">
            <div class="js-canvas-wrap">
                <canvas id="js-canvas" width="960" height="600" role="img" data-mascot="<?= $base ?>assets/images/mascot.webp"
                        aria-label="A hand-drawn cosy coffee shop on a rainy night, with a fireplace and a sleeping cat. Jell-omo, a 3D red jelly in a baseball cap, walks around the room and picks up coffee beans."></canvas>

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
                        <p class="js-start-title">Jell-omo's café</p>
                        <p class="js-start-text">It is raining, the door is closed, and the fire is on.</p>
                        <p class="js-start-hint">Click the floor to start walking</p>
                    </button>
                </div>
            </div>
            <noscript><p class="js-noscript">Jello Shop is a game that runs in your browser, so it needs JavaScript. The room and the coffee beans are drawn in the page and Jell-omo is rendered live.</p></noscript>
        </section>

        <section class="js-section" aria-labelledby="js-how">
            <h2 id="js-how">How the shop works</h2>
            <p>Click or tap anywhere on the floor and Jell-omo waddles over to that spot. While he is standing still he turns to face your cursor, so moving the mouse across the room makes him look around. The arrow keys and WASD work as well, which is handy on a laptop with a trackpad.</p>
            <p>Coffee beans appear on the floor from time to time. Walk Jell-omo over one and it is picked up, counted in the corner, and rings a note. There is no scoring system, no unlocks and nothing to spend the beans on — although the chalkboard menu behind the bar prices everything in them. Click the cat if you want it to wake up; left alone, it gets up on its own now and then. The steam over the coffee machine thickens as your bean count climbs.</p>
            <p>The shop is deliberately a single room. The door on the far wall has a hand-drawn <em>CLOSED</em> sign on it, because Jell-omo only gets one room.</p>
        </section>

        <section class="js-section" aria-labelledby="js-build">
            <h2 id="js-build">How it is made</h2>
            <p><strong>The room</strong> is painted the way Club Penguin painted its rooms: a wide back wall, narrow side walls, a floor that opens towards you, flat saturated colour with one shade and one highlight, and dark warm outlines that wobble like they were inked by hand. Jell-omo walks in a handful of depth lanes, and every piece of furniture is sorted back to front so he can pass behind a plant and in front of an armchair. The rain, the lightning, the fire, the string lights and the steam are drawn live over the top.</p>
            <p><strong>Jell-omo</strong> is a real 3D model, not a picture. A small WebGL shader ray-marches a fluted jelly and a felt baseball cap every frame, shades the jelly as translucent and glossy, and hands the result to the room as a sprite. That is what lets him turn to face the way he walks, lean into it, squash when he lands and wobble when he stops.</p>
            <p><strong>The music</strong> is a small band playing an eight-bar loop at 78 beats a minute: an electric piano, an upright bass, a vibraphone melody and brushed drums, with rain and a crackling fire under it. It changes a little every time round. All of it, and the sound effects, is synthesised with the Web Audio API — there are no audio files.</p>
        </section>

        <section class="js-section" aria-labelledby="js-cat">
            <h2 id="js-cat">How the cat decides what to do</h2>
            <p>Nobody scripted the cat. A gated recurrent unit with 12 hidden units and 1,194 weights, 4,776 bytes in all, reads 18 numbers twice a second and chooses between sleeping, sitting, walking and grooming. It was trained offline with evolution strategies and only runs forward in your browser. <a href="<?= $base ?>jelloshop/cat-brain">The inputs, the outputs and the training are laid out here</a>.</p>
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
                <li>The room, Jell-omo, the beans and the music are written for this page. No images, no audio files, no libraries, no tracking.</li>
                <li>Jell-omo is the same red jelly in the navy and cream cap that appears elsewhere on this site, rebuilt as a 3D model.</li>
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
