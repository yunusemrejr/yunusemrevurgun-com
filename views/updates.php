<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Updates.php';
require_once __DIR__ . '/includes/ui.php';

$updates = new Updates();
$updatesPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $updatesPerPage;
$allUpdates = $updates->getPaginatedUpdates($offset, $updatesPerPage);
$totalUpdates = $updates->getTotalUpdates();
$totalPages = (int) ceil($totalUpdates / $updatesPerPage);

$pageMeta = [];
if ($totalPages > 1) {
    if ($currentPage > 1) {
        $pageMeta[] = '<link rel="prev" href="' . FULL_BASE_PATH . 'updates?page=' . ($currentPage - 1) . '">';
    }
    if ($currentPage < $totalPages) {
        $pageMeta[] = '<link rel="next" href="' . FULL_BASE_PATH . 'updates?page=' . ($currentPage + 1) . '">';
    }
}

ui_render_head(
    'Journal | Updates',
    'Short notes, timestamped updates, and rapid changes by Yunus Emre Vurgun.',
    $pageMeta
);
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

            <div class="ui-feed-list">
                <?php if (!empty($allUpdates)): ?>
                    <?php foreach ($allUpdates as $update):
                        $excerpt = mb_substr(trim(strip_tags((string)($update['description'] ?? ''))), 0, 170);
                        ?>
                        <article class="ui-feed-card">
                            <div class="ui-update-row">
                                <img
                                    class="ui-update-avatar"
                                    src="<?= FULL_BASE_PATH ?>assets/images/favicon.svg"
                                    alt="Yunus Emre Vurgun"
                                    loading="lazy"
                                >
                                <div class="ui-update-body">
                                    <a href="<?= FULL_BASE_PATH . 'updates/' . intval($update['id']) ?>" style="display: block;">
                                        <h2 class="ui-feed-title"><?= htmlspecialchars($update['title']) ?></h2>
                                        <p class="ui-feed-meta"><?= date('M j, Y H:i', strtotime($update['update_date'] ?? $update['created_at'] ?? 'now')) ?></p>
                                        <p class="ui-feed-text"><?= htmlspecialchars($excerpt) ?>...</p>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="ui-empty">No updates available yet.</div>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="ui-pagination" aria-label="Updates pagination">
                    <?php if ($currentPage > 1): ?>
                        <a class="ui-page-link" href="<?= FULL_BASE_PATH ?>updates?page=<?= $currentPage - 1 ?>">Previous</a>
                    <?php else: ?>
                        <span class="ui-page-link is-disabled">Previous</span>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="ui-page-link<?= $i === $currentPage ? ' is-active' : '' ?>" href="<?= FULL_BASE_PATH ?>updates?page=<?= $i ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a class="ui-page-link" href="<?= FULL_BASE_PATH ?>updates?page=<?= $currentPage + 1 ?>">Next</a>
                    <?php else: ?>
                        <span class="ui-page-link is-disabled">Next</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
</body>
</html>
