<?php
/**
 * Public — Quality 3D AI Slop
 * Lists single-file HTML 3D experiments uploaded from the admin panel. Each
 * entry links to its own indexed page at /slop/<slug>.
 */
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/views/includes/collection.php';
require_once dirname(__DIR__, 2) . '/models/Slop.php';

$slop = new Slop();
$list = $slop->getAllSlops();

$sectionUrl = rtrim(FULL_BASE_PATH, '/') . '/slop';
$modules = array_map(fn($item) => ['title' => $item['title'] ?? 'Untitled'], $list);
$schemaItems = [];
foreach (array_values($list) as $index => $item) {
    $schemaItems[] = [
        '@type' => 'ListItem',
        'position' => $index + 1,
        'name' => $item['title'] ?? 'Untitled',
        'url' => $sectionUrl . '/' . rawurlencode($item['slug']),
    ];
}
$schema = '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => [
    ['@type' => 'CollectionPage', '@id' => $sectionUrl, 'url' => $sectionUrl, 'name' => 'Quality 3D AI Slop',
     'description' => 'Single-file HTML 3D experiments by Yunus Emre Vurgun.',
     'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $schemaItems]],
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => rtrim(FULL_BASE_PATH, '/') . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'More', 'item' => rtrim(FULL_BASE_PATH, '/') . '/more'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => 'Quality 3D AI Slop', 'item' => $sectionUrl],
    ]],
]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';

ui_render_head(
    'Quality 3D AI Slop | Yunus Emre Vurgun',
    'Single-file HTML 3D experiments by Yunus Emre Vurgun — open each slop in your browser, no install needed.',
    [$schema]
);
?>
<body class="ui-collection">
<div class="ui-page">
    <?php ui_render_navbar('more'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Experiments</p>
            <h1 class="ui-section-title">Quality 3D AI Slop</h1>
            <p class="ui-section-text">Single-file HTML pages with 3D things in them. Each one opens as its own page — pick a slop and play with it.</p>
        </section>

        <section class="ui-section" data-collection>
            <?php if (count($list) > 0): ?>
                <?php ui_collection_tools($modules); ?>
                <div class="ui-feed-list" id="slopFeed">
                    <?php foreach ($list as $item):
                        $title = $item['title'] ?? 'Untitled';
                        $url = FULL_BASE_PATH . 'slop/' . rawurlencode($item['slug']);
                        $date = !empty($item['created_at']) ? date('M d, Y', strtotime($item['created_at'])) : '';
                    ?>
                        <article class="ui-feed-card" id="slop-<?= htmlspecialchars($item['slug']) ?>" data-collection-item>
                            <div class="ui-feed-card-header">
                                <div>
                                    <h2 class="ui-feed-title"><a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($title) ?></a></h2>
                                    <?php if ($date !== ''): ?>
                                        <p class="ui-feed-meta"><?= htmlspecialchars($date) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($item['description'])): ?>
                                <p class="ui-feed-excerpt"><?= htmlspecialchars($item['description']) ?></p>
                            <?php endif; ?>
                            <p><a class="ui-more-link" href="<?= htmlspecialchars($url) ?>">Open slop →</a></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="ui-collection-empty" data-collection-empty hidden><p>No slops match. Try a shorter phrase or clear the search.</p><button type="button" class="ui-btn ui-btn-secondary" data-collection-reset>Clear search</button></div>
            <?php else: ?>
                <div class="ui-section-empty">
                    <p>No slop yet. Check back soon.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>

<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>

</body>
</html>
