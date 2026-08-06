<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Search.php';
require_once __DIR__ . '/includes/ui.php';

$query = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? 'all');

$search = new Search();
$sanitizedQuery = $search->sanitizeSearchQuery($query);
$isValidQuery = ($sanitizedQuery !== false);
$results = [];
$totalResults = 0;

if ($isValidQuery && strlen($sanitizedQuery) >= 2) {
    if ($type === 'blog') {
        $items = $search->formatSearchResults($search->searchBlogPosts($sanitizedQuery), 'blog');
        $results['blog'] = $items;
        $totalResults = count($items);
    } elseif ($type === 'updates') {
        $items = $search->formatSearchResults($search->searchUpdates($sanitizedQuery), 'updates');
        $results['updates'] = $items;
        $totalResults = count($items);
    } elseif ($type === 'portfolio') {
        $items = $search->formatSearchResults($search->searchPortfolio($sanitizedQuery), 'portfolio');
        $results['portfolio'] = $items;
        $totalResults = count($items);
    } else {
        $global = $search->globalSearch($sanitizedQuery);
        $results = [
            'blog' => $search->formatSearchResults($global['blog'], 'blog'),
            'updates' => $search->formatSearchResults($global['updates'], 'updates'),
            'portfolio' => $search->formatSearchResults($global['portfolio'], 'portfolio'),
        ];
        $totalResults = count($results['blog']) + count($results['updates']) + count($results['portfolio']);
    }
}

ui_render_head(
    'Search' . (!empty($query) ? ' — ' . htmlspecialchars($query) : '') . ' | Yunus Emre Vurgun',
    'Search results across blog posts, updates, and portfolio of Yunus Emre Vurgun.',
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar(); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Search</p>
            <h1 class="ui-section-title">Find Content</h1>
        </section>

        <section class="ui-section">
            <form class="ui-search-form" action="<?= FULL_BASE_PATH ?>search" method="get">
                <input class="ui-input" type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($query) ?>" required>
                <button class="ui-btn ui-btn-secondary" type="submit">Search</button>
            </form>

            <div class="ui-tags">
                <a class="ui-tag<?= $type === 'all' ? ' is-active' : '' ?>" href="<?= FULL_BASE_PATH ?>search?q=<?= urlencode($query) ?>&type=all">All</a>
                <a class="ui-tag<?= $type === 'blog' ? ' is-active' : '' ?>" href="<?= FULL_BASE_PATH ?>search?q=<?= urlencode($query) ?>&type=blog">Blog</a>
                <a class="ui-tag<?= $type === 'updates' ? ' is-active' : '' ?>" href="<?= FULL_BASE_PATH ?>search?q=<?= urlencode($query) ?>&type=updates">Updates</a>
                <a class="ui-tag<?= $type === 'portfolio' ? ' is-active' : '' ?>" href="<?= FULL_BASE_PATH ?>search?q=<?= urlencode($query) ?>&type=portfolio">Portfolio</a>
            </div>

            <?php if (empty($query)): ?>
                <div class="ui-empty">Enter a search term to begin.</div>
            <?php elseif (!$isValidQuery): ?>
                <div class="ui-empty">Invalid query. Use 2-60 letters, numbers, or spaces.</div>
            <?php elseif ($totalResults === 0): ?>
                <div class="ui-empty">No results found for "<?= htmlspecialchars($sanitizedQuery) ?>".</div>
            <?php else: ?>
                <p style="margin-bottom: 1.5rem; color: var(--color-text-secondary);">
                    Found <?= $totalResults ?> result<?= $totalResults === 1 ? '' : 's' ?> for "<?= htmlspecialchars($sanitizedQuery) ?>".
                </p>

                <?php if (($type === 'all' || $type === 'blog') && !empty($results['blog'])): ?>
                    <h2 style="font-family: var(--font-display); font-size: var(--text-xl); font-weight: 600; margin-bottom: 1rem;">Blog</h2>
                    <div class="ui-grid" style="margin-bottom: 2rem;">
                        <?php foreach ($results['blog'] as $item): ?>
                            <a class="ui-link-card" href="<?= htmlspecialchars($item['url']) ?>">
                                <h3 class="ui-card-title"><?= html_entity_decode($item['title']) ?></h3>
                                <p class="ui-card-text"><?= htmlspecialchars(mb_substr(strip_tags(html_entity_decode($item['excerpt'] ?? '')), 0, 140)) ?>...</p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (($type === 'all' || $type === 'updates') && !empty($results['updates'])): ?>
                    <h2 style="font-family: var(--font-display); font-size: var(--text-xl); font-weight: 600; margin-bottom: 1rem;">Updates</h2>
                    <div class="ui-grid" style="margin-bottom: 2rem;">
                        <?php foreach ($results['updates'] as $item): ?>
                            <a class="ui-link-card" href="<?= FULL_BASE_PATH . 'updates/' . intval($item['id']) ?>">
                                <h3 class="ui-card-title"><?= html_entity_decode($item['title']) ?></h3>
                                <p class="ui-card-text"><?= htmlspecialchars(mb_substr(strip_tags(html_entity_decode($item['content'] ?? '')), 0, 140)) ?>...</p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (($type === 'all' || $type === 'portfolio') && !empty($results['portfolio'])): ?>
                    <h2 style="font-family: var(--font-display); font-size: var(--text-xl); font-weight: 600; margin-bottom: 1rem;">Portfolio</h2>
                    <div class="ui-grid">
                        <?php foreach ($results['portfolio'] as $item): ?>
                            <a class="ui-link-card" href="<?= FULL_BASE_PATH ?>portfolio">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img class="ui-media" src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                                <?php endif; ?>
                                <h3 class="ui-card-title"><?= html_entity_decode($item['title']) ?></h3>
                                <p class="ui-card-text"><?= htmlspecialchars(mb_substr(strip_tags(html_entity_decode($item['description'] ?? '')), 0, 140)) ?>...</p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
