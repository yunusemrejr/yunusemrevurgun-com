<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Rmrp.php';
require_once dirname(__DIR__) . '/models/RichText.php';
require_once __DIR__ . '/includes/ui.php';

$rmrp = new Rmrp();

// View options: grid/list layout and page size (no "show all" — unbounded
// lists are bad for both UX and crawlers). Both live in the URL so they are
// shareable and server-rendered; assets/js/rmrp.js only upgrades the
// interaction (instant toggle + localStorage memory).
$perOptions = [10, 20, 30, 50];
$per = (int) ($_GET['per'] ?? 0);
$memoriesPerPage = in_array($per, $perOptions, true) ? $per : 10;
$view = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';

$totalMemories = $rmrp->getTotalMemories();
$totalPages = max(1, (int) ceil($totalMemories / $memoriesPerPage));
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
if ($currentPage > $totalPages) {
    require __DIR__ . '/404.php';
    return;
}
$offset = ($currentPage - 1) * $memoriesPerPage;
$allMemories = $rmrp->getPaginatedMemories($offset, $memoriesPerPage);

// Query-string helper so pagination / view toggles always keep view+per.
$qs = function (array $overrides = []) use ($view, $memoriesPerPage): string {
    $params = array_merge(['view' => $view, 'per' => $memoriesPerPage], $overrides);
    return '?' . http_build_query($params);
};

$canonicalParams = [];
if ($memoriesPerPage !== 10) $canonicalParams['per'] = $memoriesPerPage;
if ($currentPage > 1) $canonicalParams['page'] = $currentPage;
$pageMeta = ['canonical' => FULL_BASE_PATH . 'rmrp' . ($canonicalParams ? '?' . http_build_query($canonicalParams) : '')];
if ($totalPages > 1) {
    if ($currentPage > 1) {
        $pageMeta[] = '<link rel="prev" href="' . FULL_BASE_PATH . 'rmrp' . $qs(['page' => $currentPage - 1]) . '">';
    }
    if ($currentPage < $totalPages) {
        $pageMeta[] = '<link rel="next" href="' . FULL_BASE_PATH . 'rmrp' . $qs(['page' => $currentPage + 1]) . '">';
    }
}
// RSS feed for the memories log (discoverability: feed readers, search
// engines and AI crawlers can subscribe to fresh entries).
$pageMeta[] = '<link rel="alternate" type="application/rss+xml" title="Random Memories RSS — Yunus Emre Vurgun" href="' . FULL_BASE_PATH . 'rmrp.xml">';

// ItemList structured data for this page of entries.
$listItems = [];
$position = ($currentPage - 1) * $memoriesPerPage + 1;
foreach ($allMemories as $memory) {
    $listItems[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'url' => rtrim(FULL_BASE_PATH, '/') . '/rmrp/' . (int) $memory['id'],
        'name' => (string) $memory['title'],
    ];
}
$pageMeta[] = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => 'Random Memories for Random People by Yunus Emre Vurgun',
    'itemListElement' => $listItems,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';

// Page-specific stylesheet + view-options script (loaded after ui-rebuild.css).
$rmrpCss = FULL_BASE_PATH . 'assets/css/rmrp.css?v=' . filemtime(dirname(__DIR__) . '/assets/css/rmrp.css');
$rmrpJs = FULL_BASE_PATH . 'assets/js/rmrp.js?v=' . filemtime(dirname(__DIR__) . '/assets/js/rmrp.js');

$pageMeta[] = '<link rel="stylesheet" href="' . $rmrpCss . '">';
$pageMeta[] = '<script src="' . $rmrpJs . '" defer></script>';

$title = 'Random Memories for Random People — Yunus Emre Vurgun';
$description = 'Fragments, fleeting thoughts, and small moments worth keeping — ' . $totalMemories . ' random memories by Yunus Emre Vurgun, with an RSS feed at /rmrp.xml.';

ui_render_head($title, $description, $pageMeta);
?>
<body class="ui-collection">
<div class="ui-page">
    <?php ui_render_navbar('Journal'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Memories</p>
            <h1 class="ui-section-title">Random Memories for Random People</h1>
            <p class="ui-section-text">Fragments, fleeting thoughts, and small moments worth keeping anyway.</p>
        </section>

        <section class="ui-section">
            <?php if (!empty($allMemories)): ?>
                <div class="ui-rmrp-toolbar">
                    <span class="ui-rmrp-count"><?= $totalMemories ?> memories</span>
                    <nav class="ui-view-toggle" aria-label="View options">
                        <a href="<?= FULL_BASE_PATH ?>rmrp<?= $qs(['view' => 'grid', 'page' => 1]) ?>"
                           data-rmrp-view="grid"
                           class="<?= $view === 'grid' ? 'is-active' : '' ?>"
                           aria-pressed="<?= $view === 'grid' ? 'true' : 'false' ?>">Grid</a>
                        <a href="<?= FULL_BASE_PATH ?>rmrp<?= $qs(['view' => 'list', 'page' => 1]) ?>"
                           data-rmrp-view="list"
                           class="<?= $view === 'list' ? 'is-active' : '' ?>"
                           aria-pressed="<?= $view === 'list' ? 'true' : 'false' ?>">List</a>
                    </nav>
                    <form class="ui-rmrp-per" method="get" action="<?= FULL_BASE_PATH ?>rmrp">
                        <input type="hidden" name="view" value="<?= $view ?>">
                        <label for="uiRmrpPerPage">Per page</label>
                        <select id="uiRmrpPerPage" name="per" data-rmrp-per>
                            <?php foreach ($perOptions as $n): ?>
                                <option value="<?= $n ?>"<?= $n === $memoriesPerPage ? ' selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="ui-btn ui-btn-secondary ui-per-apply" type="submit">Apply</button>
                    </form>
                </div>

                <div class="ui-rmrp-grid<?= $view === 'grid' ? ' is-grid' : ' is-list' ?>" data-view="<?= $view ?>">
                    <?php foreach ($allMemories as $memory):
                        $plainText = RichText::plainText($memory['description'] ?? '');
                        $excerpt = mb_substr($plainText, 0, 170);
                        $excerptIsTruncated = mb_strlen($plainText) > 170;
                        $dateRaw = $memory['memory_date'] ?? $memory['created_at'] ?? 'now';
                        ?>
                        <article class="ui-rmrp-card">
                            <div class="ui-rmrp-body">
                                <?php if (!empty($memory['category']) || !empty($memory['importance'])): ?>
                                    <div class="ui-rmrp-badges">
                                        <?php if (!empty($memory['category'])): ?>
                                            <span class="ui-tag"><?= htmlspecialchars($memory['category']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($memory['importance'])): ?>
                                            <span class="ui-importance is-<?= htmlspecialchars($memory['importance']) ?>"><?= htmlspecialchars(ucfirst($memory['importance'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <p class="ui-rmrp-meta">
                                    <time datetime="<?= date('c', strtotime($dateRaw)) ?>"><?= date('M j, Y H:i', strtotime($dateRaw)) ?></time>
                                </p>
                                <h2 class="ui-feed-title">
                                    <a href="<?= FULL_BASE_PATH ?>rmrp/<?= intval($memory['id']) ?>"><?= htmlspecialchars($memory['title']) ?></a>
                                </h2>
                                <p class="ui-feed-text"><?= htmlspecialchars($excerpt) ?><?= $excerptIsTruncated ? '…' : '' ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="ui-pagination" aria-label="Memories pagination">
                        <?php if ($currentPage > 1): ?>
                            <a class="ui-page-link" href="<?= FULL_BASE_PATH ?>rmrp<?= $qs(['page' => $currentPage - 1]) ?>">Previous</a>
                        <?php else: ?>
                            <span class="ui-page-link is-disabled">Previous</span>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a class="ui-page-link<?= $i === $currentPage ? ' is-active' : '' ?>"<?= $i === $currentPage ? ' aria-current="page"' : '' ?> href="<?= FULL_BASE_PATH ?>rmrp<?= $qs(['page' => $i]) ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($currentPage < $totalPages): ?>
                            <a class="ui-page-link" href="<?= FULL_BASE_PATH ?>rmrp<?= $qs(['page' => $currentPage + 1]) ?>">Next</a>
                        <?php else: ?>
                            <span class="ui-page-link is-disabled">Next</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="ui-empty">No memories yet.</div>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
