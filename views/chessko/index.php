<?php
/**
 * /chessko: the game, plus the text that explains it. Search engines get a real page here (an
 * h1, an introduction, the article index, an FAQ and structured data), not just a canvas.
 */
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/models/Socials.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/views/includes/seo.php';

$articles = require __DIR__ . '/articles.php';
$base = FULL_BASE_PATH;
$origin = rtrim(FULL_BASE_PATH, '/');
$pageUrl = $origin . '/chessko';
$basePath = rtrim((string)parse_url(FULL_BASE_PATH, PHP_URL_PATH), '/');
$assetsUrl = $basePath . '/assets/chessko';
$apiUrl = $basePath . '/api/chessko';
$appJs = dirname(__DIR__, 2) . '/assets/chessko/js/app.js';
$modified = date('c', max(filemtime(__FILE__), filemtime(__DIR__ . '/articles.php'), (int)@filemtime($appJs)));

$title = 'Play Chess vs Stockfish in Your Browser – Chessko, No Sign-Up';
$description = 'Play Chessko, a free browser chess game with wobbling jelly pieces. Seven levels, from a wobbly beginner bot to Stockfish 19 running as WebAssembly. No sign-up.';

$faq = [
    ['Is Chessko free to play?', 'Yes. Chessko is free, needs no account or download, and runs in any modern browser with JavaScript and WebAssembly.'],
    ['Does Chessko send my moves to a server?', 'No. The rules, the search and Stockfish all run in your browser. When a game ends, only its result, the level, the side you played and the number of moves are stored, under a random anonymous id kept in your browser. No IP address is stored.'],
    ['How strong is the computer?', 'It depends on the level. Levels 1 to 5 are a small home-grown engine that is made to wobble, from very beatable to always playing its best move. Level 6 is Stockfish 19 limited to UCI_Elo 2000, and level 7 is Stockfish 19 at full strength.'],
    ['Can Stockfish really run in a browser?', 'Yes. Chessko runs a 1.8 MB single-threaded WebAssembly build of Stockfish 19 in a Web Worker. It downloads only when you pick a Stockfish level or ask for a hint.'],
    ['What if my browser does not support WebAssembly?', 'Chessko falls back to its own search at full effort and tells you Stockfish could not start.'],
    ['Why is it made of jelly?', 'The look comes from the name of the game: translucent lime and strawberry squares, and milk and blackcurrant pieces that wobble when they move. Everything that animates reflects something real, such as your turn, the engine thinking, or the level in effect.'],
];

$schemaGraph = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebApplication',
            '@id' => $pageUrl . '#app',
            'name' => 'Chessko',
            'alternateName' => 'Chessko jelly chess',
            'url' => $pageUrl,
            'description' => $description,
            'applicationCategory' => 'GameApplication',
            'genre' => 'Chess',
            'operatingSystem' => 'Any (modern web browser)',
            'browserRequirements' => 'Requires JavaScript and WebAssembly',
            'isAccessibleForFree' => true,
            'inLanguage' => 'en',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'featureList' => [
                'Seven difficulty levels',
                'Stockfish 19 running as WebAssembly in a Web Worker',
                'Home-grown alpha-beta search with a learned evaluation',
                'Hints from full-strength Stockfish',
                'Undo, flip sides and FEN position loading',
                'Difficulty that adapts to your play',
            ],
            'author' => ['@type' => 'Person', 'name' => 'Yunus Emre Vurgun', 'url' => $origin . '/about'],
            'dateModified' => $modified,
            'about' => seo_things(['chess_engine', 'stockfish']),
            'mentions' => seo_things(['webassembly', 'web_worker', 'alpha_beta', 'uci']),
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
                ['@type' => 'ListItem', 'position' => 3, 'name' => 'Chessko', 'item' => $pageUrl],
            ],
        ],
    ],
];

ui_render_head($title, $description, [
    'chessko' => 'game',
    'og_image' => FULL_BASE_PATH . 'assets/images/og-chessko.png?v=' . (@filemtime(dirname(__DIR__, 2) . '/assets/images/og-chessko.png') ?: 1),
    '<meta property="og:type" content="website">',
    '<meta name="keywords" content="chess, play chess online, free chess game, chess vs computer, Stockfish, Stockfish WebAssembly, browser chess, jelly chess">',
    '<script type="application/ld+json">' . json_encode($schemaGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>',
    '<link rel="preload" href="' . $assetsUrl . '/fonts/baloo-2-latin-700-normal.woff2" as="font" type="font/woff2" crossorigin>',
    '<link rel="modulepreload" href="' . $assetsUrl . '/js/app.js?v=' . (@filemtime($appJs) ?: 1) . '">',
]);
?>
<body class="ui-chessko-page">
<div class="ui-page">
    <?php ui_render_navbar('more'); ?>

    <main class="ui-main ck-page" id="main-content" tabindex="-1">
        <nav class="ck-crumbs" aria-label="Breadcrumb">
            <ol>
                <li><a href="<?= $base ?>">Home</a></li>
                <li><a href="<?= $base ?>more">More</a></li>
                <li aria-current="page">Chessko</li>
            </ol>
        </nav>

        <header class="ck-head">
            <h1 class="ck-title">Chessko: play jelly chess against Stockfish in your browser</h1>
            <p class="ck-lead">A free chess game with seven levels. The gentle ones are a small engine that wobbles on purpose; the top two hand the move to Stockfish 19, compiled to WebAssembly and running on your device. No sign-up, and your moves never leave the page.</p>
        </header>

        <section class="ck-stage" aria-label="Play chess">
            <?php require __DIR__ . '/_game.php'; ?>
            <noscript><p class="ck-noscript">Chessko is a chess game that runs in your browser, so it needs JavaScript. Everything explained below still applies once you enable it.</p></noscript>
        </section>

        <section class="ck-section" aria-labelledby="ck-how">
            <h2 id="ck-how">How Chessko plays</h2>
            <p>Click a piece, then a square, or drag it. Pick a level under <em>squishiness</em>: levels 1 to 5 use Chessko's own search and get steadily less wobbly; level 6, <em>Gelatitan</em>, is Stockfish held to a 2000 Elo cap; level 7, <em>Set in Stone</em>, is Stockfish at full strength. The <em>hint</em> button always asks full-strength Stockfish. The bar under <em>chessko's opinion</em> shows who the trained evaluation thinks is ahead.</p>
            <p>Everything is explained in short articles, with the real numbers from the code:</p>
            <ul class="ck-articles" role="list">
                <?php foreach ($articles as $slug => $a): ?>
                <li>
                    <a href="<?= $base ?>chessko/<?= htmlspecialchars($slug) ?>">
                        <span class="ck-articles-title"><?= htmlspecialchars($a['h1']) ?></span>
                        <span class="ck-articles-desc"><?= htmlspecialchars($a['description']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="ck-section" aria-labelledby="ck-faq">
            <h2 id="ck-faq">Chessko FAQ</h2>
            <dl class="ck-faq">
                <?php foreach ($faq as [$q, $a]): ?>
                <div>
                    <dt><?= htmlspecialchars($q) ?></dt>
                    <dd><?= htmlspecialchars($a) ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
        </section>

        <section class="ck-section" aria-labelledby="ck-credits">
            <h2 id="ck-credits">Credits and licences</h2>
            <ul class="ck-credits">
                <li><a href="https://github.com/official-stockfish/Stockfish" rel="noopener" target="_blank">Stockfish</a> 19 via <a href="https://github.com/nmrugg/stockfish.js" rel="noopener" target="_blank">stockfish.js</a>, GNU GPL v3 (<a href="<?= $base ?>assets/chessko/vendor/stockfish/COPYING.txt">licence text</a>). Unmodified lite single-threaded build.</li>
                <li><a href="https://github.com/catdad/canvas-confetti" rel="noopener" target="_blank">canvas-confetti</a> (ISC) for the win celebration.</li>
                <li>Baloo 2 and Nunito, SIL Open Font License, served from this site.</li>
                <li>The chess rules engine, search, evaluation and jelly are written for this project. The page stores a random id in your browser's local storage so your record survives a reload; nothing else is stored.</li>
            </ul>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<script>window.CHESSKO = <?= json_encode(['assets' => $assetsUrl, 'api' => $apiUrl], JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= $assetsUrl ?>/vendor/confetti.browser.js" defer></script>
<script type="module" src="<?= $assetsUrl ?>/js/app.js?v=<?= @filemtime($appJs) ?: 1 ?>"></script>
<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
</body>
</html>
