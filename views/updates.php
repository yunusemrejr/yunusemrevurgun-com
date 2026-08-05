<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Updates.php';
require_once dirname(__DIR__) . '/models/RichText.php';
require_once __DIR__ . '/includes/ui.php';

$updates = new Updates();

// View options: grid/list layout and page size (no "show all" — unbounded
// lists are bad for both UX and crawlers). Both live in the URL so they are
// shareable and server-rendered; assets/js/updates.js only upgrades the
// interaction (instant toggle + localStorage memory).
$perOptions = [10, 20, 30, 50];
$per = (int) ($_GET['per'] ?? 0);
$updatesPerPage = in_array($per, $perOptions, true) ? $per : 10;
$view = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';

$totalUpdates = $updates->getTotalUpdates();
$totalPages = max(1, (int) ceil($totalUpdates / $updatesPerPage));
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $updatesPerPage;
$allUpdates = $updates->getPaginatedUpdates($offset, $updatesPerPage);

// Query-string helper so pagination / view toggles always keep view+per.
$qs = function (array $overrides = []) use ($view, $updatesPerPage): string {
    $params = array_merge(['view' => $view, 'per' => $updatesPerPage], $overrides);
    return '?' . http_build_query($params);
};

$pageMeta = [];
if ($totalPages > 1) {
    if ($currentPage > 1) {
        $pageMeta[] = '<link rel="prev" href="' . FULL_BASE_PATH . 'updates' . $qs(['page' => $currentPage - 1]) . '">';
    }
    if ($currentPage < $totalPages) {
        $pageMeta[] = '<link rel="next" href="' . FULL_BASE_PATH . 'updates' . $qs(['page' => $currentPage + 1]) . '">';
    }
}
// RSS feed for the updates log (discoverability: feed readers, search
// engines and AI crawlers can subscribe to fresh entries).
$pageMeta[] = '<link rel="alternate" type="application/rss+xml" title="Updates RSS — Yunus Emre Vurgun" href="' . FULL_BASE_PATH . 'updates.xml">';

// ItemList structured data for this page of entries.
$listItems = [];
$position = ($currentPage - 1) * $updatesPerPage + 1;
foreach ($allUpdates as $update) {
    $listItems[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'url' => rtrim(FULL_BASE_PATH, '/') . '/updates/' . (int) $update['id'],
        'name' => (string) $update['title'],
    ];
}
$pageMeta[] = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => 'Updates by Yunus Emre Vurgun',
    'itemListElement' => $listItems,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';

// Page-specific stylesheet + view-options script (loaded after ui-rebuild.css).
$updatesCss = FULL_BASE_PATH . 'assets/css/updates.css?v=' . filemtime(dirname(__DIR__) . '/assets/css/updates.css');
$updatesJs = FULL_BASE_PATH . 'assets/js/updates.js?v=' . filemtime(dirname(__DIR__) . '/assets/js/updates.js');

$pageMeta[] = '<link rel="stylesheet" href="' . $updatesCss . '">';
$pageMeta[] = '<script src="' . $updatesJs . '" defer></script>';

$title = 'Updates — Journal by Yunus Emre Vurgun';
$description = 'Fresh notes and rapid changes by Yunus Emre Vurgun — ' . $totalUpdates . ' timestamped entries, searchable, with an RSS feed at /updates.xml.';

ui_render_head($title, $description, $pageMeta);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Journal'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Log</p>
            <h1 class="ui-section-title">Updates</h1>
            <p class="ui-section-text">Short notes, timestamped entries, and rapid changes.</p>
        </section>

        <section class="ui-section">
            <form class="ui-search-form" action="<?= FULL_BASE_PATH ?>search" method="get">
                <input class="ui-input" type="text" name="q" placeholder="Search updates..." required>
                <input type="hidden" name="type" value="updates">
                <button class="ui-btn ui-btn-secondary" type="submit">Search</button>
            </form>

            <?php if (!empty($allUpdates)): ?>
                <div class="ui-updates-toolbar">
                    <span class="ui-updates-count"><?= $totalUpdates ?> entries</span>
                    <nav class="ui-view-toggle" aria-label="View options">
                        <a href="<?= FULL_BASE_PATH ?>updates<?= $qs(['view' => 'grid', 'page' => 1]) ?>"
                           data-updates-view="grid"
                           class="<?= $view === 'grid' ? 'is-active' : '' ?>"
                           aria-pressed="<?= $view === 'grid' ? 'true' : 'false' ?>">Grid</a>
                        <a href="<?= FULL_BASE_PATH ?>updates<?= $qs(['view' => 'list', 'page' => 1]) ?>"
                           data-updates-view="list"
                           class="<?= $view === 'list' ? 'is-active' : '' ?>"
                           aria-pressed="<?= $view === 'list' ? 'true' : 'false' ?>">List</a>
                    </nav>
                    <form class="ui-updates-per" method="get" action="<?= FULL_BASE_PATH ?>updates">
                        <input type="hidden" name="view" value="<?= $view ?>">
                        <label for="uiPerPage">Per page</label>
                        <select id="uiPerPage" name="per" data-updates-per>
                            <?php foreach ($perOptions as $n): ?>
                                <option value="<?= $n ?>"<?= $n === $updatesPerPage ? ' selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="ui-btn ui-btn-secondary ui-per-apply" type="submit">Apply</button>
                    </form>
                </div>

                <div class="ui-updates-grid<?= $view === 'grid' ? ' is-grid' : ' is-list' ?>" data-view="<?= $view ?>">
                    <?php foreach ($allUpdates as $update):
                        $plainText = RichText::plainText($update['description'] ?? '');
                        $excerpt = mb_substr($plainText, 0, 170);
                        $excerptIsTruncated = mb_strlen($plainText) > 170;
                        $dateRaw = $update['update_date'] ?? $update['created_at'] ?? 'now';
                        ?>
                        <article class="ui-update-card">
                            <div class="ui-update-row">
                                <img
                                    class="ui-update-avatar"
                                    src="<?= FULL_BASE_PATH ?>assets/images/favicon.svg"
                                    alt="Yunus Emre Vurgun"
                                    loading="lazy"
                                >
                                <div class="ui-update-body">
                                    <?php if (!empty($update['category']) || !empty($update['importance'])): ?>
                                        <div class="ui-update-badges">
                                            <?php if (!empty($update['category'])): ?>
                                                <span class="ui-tag"><?= htmlspecialchars($update['category']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($update['importance'])): ?>
                                                <span class="ui-importance is-<?= htmlspecialchars($update['importance']) ?>"><?= htmlspecialchars(ucfirst($update['importance'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <h2 class="ui-feed-title">
                                        <a href="<?= FULL_BASE_PATH ?>updates/<?= intval($update['id']) ?>"><?= htmlspecialchars($update['title']) ?></a>
                                    </h2>
                                    <p class="ui-feed-meta">
                                        <time datetime="<?= date('c', strtotime($dateRaw)) ?>"><?= date('M j, Y H:i', strtotime($dateRaw)) ?></time>
                                    </p>
                                    <p class="ui-feed-text"><?= htmlspecialchars($excerpt) ?><?= $excerptIsTruncated ? '…' : '' ?></p>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="ui-pagination" aria-label="Updates pagination">
                        <?php if ($currentPage > 1): ?>
                            <a class="ui-page-link" href="<?= FULL_BASE_PATH ?>updates<?= $qs(['page' => $currentPage - 1]) ?>">Previous</a>
                        <?php else: ?>
                            <span class="ui-page-link is-disabled">Previous</span>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a class="ui-page-link<?= $i === $currentPage ? ' is-active' : '' ?>" href="<?= FULL_BASE_PATH ?>updates<?= $qs(['page' => $i]) ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($currentPage < $totalPages): ?>
                            <a class="ui-page-link" href="<?= FULL_BASE_PATH ?>updates<?= $qs(['page' => $currentPage + 1]) ?>">Next</a>
                        <?php else: ?>
                            <span class="ui-page-link is-disabled">Next</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="ui-empty">No updates available yet.</div>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
