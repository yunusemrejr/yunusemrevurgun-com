<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Rmrp.php';
require_once dirname(__DIR__) . '/models/RichText.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/visuals.php';

$rmrp = new Rmrp();

// View options: grid/list layout and page size (no "show all" — unbounded
// lists are bad for both UX and crawlers). Both live in the URL so they are
// shareable and server-rendered; assets/js/rmrp.js only upgrades the
// interaction (instant toggle + localStorage memory).
$perOptions = [10, 20, 30, 50];
$per = (int) ($_GET['per'] ?? 0);
$memoriesPerPage = in_array($per, $perOptions, true) ? $per : 10;
$view = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';

// Every memory is enriched on the fly (topic, reading time, key figures) so the
// listing can be filtered by topic and searched without any stored metadata.
$everything = [];
foreach ($rmrp->getAllMemories() as $m) {
    $plain = RichText::plainText($m['description'] ?? '');
    $m['_plain'] = $plain;
    $m['_topic'] = vis_classify(($m['title'] ?? '') . ' ' . $plain);
    $everything[] = $m;
}
$grandTotal = count($everything);

$topicCounts = [];
foreach ($everything as $m) {
    $topicCounts[$m['_topic']] = ($topicCounts[$m['_topic']] ?? 0) + 1;
}
arsort($topicCounts);

$topic = (string) ($_GET['topic'] ?? '');
if (!isset($topicCounts[$topic])) $topic = '';
$search = trim((string) ($_GET['q'] ?? ''));
$search = mb_substr($search, 0, 80);

$filtered = array_values(array_filter($everything, function ($m) use ($topic, $search) {
    if ($topic !== '' && $m['_topic'] !== $topic) return false;
    if ($search !== '' && mb_stripos(($m['title'] ?? '') . ' ' . $m['_plain'] . ' ' . ($m['category'] ?? ''), $search) === false) return false;
    return true;
}));

$totalMemories = count($filtered);
$totalPages = max(1, (int) ceil($totalMemories / $memoriesPerPage));
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
if ($currentPage > $totalPages) {
    require __DIR__ . '/404.php';
    return;
}
$offset = ($currentPage - 1) * $memoriesPerPage;
$allMemories = array_slice($filtered, $offset, $memoriesPerPage);
$isFiltered = $topic !== '' || $search !== '';

$lastRow = $everything ? $everything[count($everything) - 1] : null;
$firstDate = $lastRow ? ($lastRow['memory_date'] ?? $lastRow['created_at'] ?? null) : null;
$latestDate = $everything ? ($everything[0]['memory_date'] ?? $everything[0]['created_at'] ?? null) : null;
$monthAgo = strtotime('-30 days');
$recentCount = 0;
foreach ($everything as $m) {
    if (strtotime($m['memory_date'] ?? $m['created_at'] ?? 'now') >= $monthAgo) $recentCount++;
}
$randomIds = implode(',', array_map(static fn($m) => (int) $m['id'], $everything));

// Query-string helper so pagination / view toggles always keep view+per.
$qs = function (array $overrides = []) use ($view, $memoriesPerPage, $topic, $search): string {
    $params = array_merge(['view' => $view, 'per' => $memoriesPerPage, 'topic' => $topic, 'q' => $search], $overrides);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
};

$canonicalParams = [];
if ($memoriesPerPage !== 10) $canonicalParams['per'] = $memoriesPerPage;
if ($topic !== '') $canonicalParams['topic'] = $topic;
if ($currentPage > 1) $canonicalParams['page'] = $currentPage;
$noindex = $search !== '';
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
    'numberOfItems' => $totalMemories,
    'itemListElement' => $listItems,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';

// Page-specific stylesheet + view-options script (loaded after ui-rebuild.css).
$rmrpCss = FULL_BASE_PATH . 'assets/css/rmrp.css?v=' . filemtime(dirname(__DIR__) . '/assets/css/rmrp.css');
$rmrpJs = FULL_BASE_PATH . 'assets/js/rmrp.js?v=' . filemtime(dirname(__DIR__) . '/assets/js/rmrp.js');

$pageMeta[] = '<link rel="stylesheet" href="' . FULL_BASE_PATH . 'assets/css/visuals.css?v=' . filemtime(dirname(__DIR__) . '/assets/css/visuals.css') . '">';
$pageMeta[] = '<link rel="stylesheet" href="' . $rmrpCss . '">';
if ($noindex) $pageMeta['robots'] = 'noindex,follow';
$pageMeta[] = '<script src="' . $rmrpJs . '" defer></script>';
$pageMeta[] = '<script src="' . FULL_BASE_PATH . 'assets/js/rmrp-extras.js?v=' . filemtime(dirname(__DIR__) . '/assets/js/rmrp-extras.js') . '" defer></script>';

$title = 'Random Memories for Random People — Yunus Emre Vurgun';
$description = 'Fragments, fleeting thoughts, and small moments worth keeping — ' . $grandTotal . ' random memories by Yunus Emre Vurgun, with an RSS feed at /rmrp.xml.';

ui_render_head($title, $description, $pageMeta);
?>
<body class="ui-collection ui-rmrp-page">
<div class="ui-page">
    <?php ui_render_navbar('Journal'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section ui-rmrp-hero">
            <div class="ui-rmrp-hero-copy">
                <p class="ui-eyebrow">Memories</p>
                <h1 class="ui-section-title">Random Memories for Random People</h1>
                <p class="ui-section-text">Things I learned and wanted to keep — a black hole with a 94-year orbit, a library that leaked keys, a layer of rock under Bermuda. Short, sourced-by-curiosity, and sorted for you automatically.</p>
                <div class="ui-rmrp-hero-actions">
                    <?php if ($randomIds !== ''): ?>
                    <a class="ui-btn ui-btn-primary" href="<?= FULL_BASE_PATH ?>rmrp/<?= (int) $everything[0]['id'] ?>" data-random-memory data-ids="<?= htmlspecialchars($randomIds) ?>">Surprise me</a>
                    <?php endif; ?>
                    <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>rmrp.xml">RSS feed</a>
                </div>
            </div>
            <dl class="ui-rmrp-stats">
                <div><dt>Memories</dt><dd data-count="<?= $grandTotal ?>"><?= $grandTotal ?></dd></div>
                <div><dt>Topics</dt><dd data-count="<?= count($topicCounts) ?>"><?= count($topicCounts) ?></dd></div>
                <?php if ($firstDate): ?>
                <div><dt>Since</dt><dd class="is-date"><?= date('M Y', strtotime($firstDate)) ?></dd></div>
                <?php endif; ?>
                <?php if ($latestDate): ?>
                <div><dt>Latest</dt><dd class="is-date"><?= date('M j', strtotime($latestDate)) ?></dd></div>
                <?php endif; ?>
            </dl>
        </section>

        <section class="ui-section">
            <?php if ($grandTotal > 0): ?>
                <div class="ui-rmrp-topics" role="navigation" aria-label="Filter memories by topic">
                    <a class="ui-rmrp-chip<?= $topic === '' ? ' is-active' : '' ?>" href="<?= FULL_BASE_PATH ?>rmrp<?= $qs(['topic' => '', 'page' => 1]) ?>"<?= $topic === '' ? ' aria-current="true"' : '' ?>>All <span><?= $grandTotal ?></span></a>
                    <?php foreach ($topicCounts as $key => $count): ?>
                        <a class="ui-rmrp-chip vis-chip--<?= htmlspecialchars($key) ?><?= $topic === $key ? ' is-active' : '' ?>" href="<?= FULL_BASE_PATH ?>rmrp<?= $qs(['topic' => $key, 'page' => 1]) ?>"<?= $topic === $key ? ' aria-current="true"' : '' ?>><?= htmlspecialchars(vis_topic_label($key)) ?> <span><?= $count ?></span></a>
                    <?php endforeach; ?>
                </div>

                <div class="ui-rmrp-toolbar">
                    <form class="ui-rmrp-search" method="get" action="<?= FULL_BASE_PATH ?>rmrp" role="search">
                        <input type="hidden" name="view" value="<?= $view ?>">
                        <input type="hidden" name="per" value="<?= $memoriesPerPage ?>">
                        <?php if ($topic !== ''): ?><input type="hidden" name="topic" value="<?= htmlspecialchars($topic) ?>"><?php endif; ?>
                        <label class="ui-sr-only" for="uiRmrpSearch">Search memories</label>
                        <input type="search" id="uiRmrpSearch" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search memories" autocomplete="off" maxlength="80">
                        <button class="ui-btn ui-btn-secondary" type="submit">Search</button>
                    </form>
                    <span class="ui-rmrp-count" role="status"><?= $isFiltered ? $totalMemories . ' of ' . $grandTotal : $totalMemories ?> memories</span>
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
                        <?php if ($topic !== ''): ?><input type="hidden" name="topic" value="<?= htmlspecialchars($topic) ?>"><?php endif; ?>
                        <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <label for="uiRmrpPerPage">Per page</label>
                        <select id="uiRmrpPerPage" name="per" data-rmrp-per>
                            <?php foreach ($perOptions as $n): ?>
                                <option value="<?= $n ?>"<?= $n === $memoriesPerPage ? ' selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="ui-btn ui-btn-secondary ui-per-apply" type="submit">Apply</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!empty($allMemories)): ?>
                <div class="ui-rmrp-grid<?= $view === 'grid' ? ' is-grid' : ' is-list' ?>" data-view="<?= $view ?>">
                    <?php foreach ($allMemories as $idx => $memory):
                        $plainText = $memory['_plain'];
                        // Titles are often the opening words of the text; don't repeat them.
                        $body = $plainText;
                        if (mb_stripos($body, $memory['title']) === 0) {
                            $body = ltrim(mb_substr($body, mb_strlen($memory['title'])), " \t\n\r.,;:—-");
                        }
                        $excerpt = mb_substr($body, 0, 170);
                        $excerptIsTruncated = mb_strlen($body) > 170;
                        $dateRaw = $memory['memory_date'] ?? $memory['created_at'] ?? 'now';
                        $mTopic = $memory['_topic'];
                        $figures = vis_key_numbers($plainText, 2);
                        $isFeature = $idx === 0 && $currentPage === 1 && !$isFiltered;
                        $cat = trim((string) ($memory['category'] ?? ''));
                        $showCat = $cat !== '' && mb_strlen($cat) <= 22 && mb_stripos($memory['title'], $cat) === false;
                        ?>
                        <article class="ui-rmrp-card<?= $isFeature ? ' is-feature' : '' ?>" data-reveal>
                            <a class="ui-rmrp-cover" href="<?= FULL_BASE_PATH ?>rmrp/<?= intval($memory['id']) ?>" tabindex="-1" aria-hidden="true">
                                <?= vis_cover($mTopic, (int) $memory['id']) ?>
                                <span class="ui-rmrp-topic-pill"><?= htmlspecialchars(vis_topic_label($mTopic)) ?></span>
                                <?php if ($isFeature): ?><span class="ui-rmrp-latest">Latest</span><?php endif; ?>
                            </a>
                            <div class="ui-rmrp-body">
                                <p class="ui-rmrp-meta">
                                    <time datetime="<?= date('c', strtotime($dateRaw)) ?>"><?= date('M j, Y', strtotime($dateRaw)) ?></time>
                                    <span aria-hidden="true">·</span>
                                    <span><?= vis_reading_minutes($plainText) ?> min read</span>
                                    <?php if ($showCat): ?><span aria-hidden="true">·</span><span><?= htmlspecialchars($cat) ?></span><?php endif; ?>
                                </p>
                                <h2 class="ui-feed-title">
                                    <a href="<?= FULL_BASE_PATH ?>rmrp/<?= intval($memory['id']) ?>"><?= htmlspecialchars($memory['title']) ?></a>
                                </h2>
                                <?php if ($excerpt !== ''): ?><p class="ui-feed-text"><?= htmlspecialchars($excerpt) ?><?= $excerptIsTruncated ? '…' : '' ?></p><?php endif; ?>
                                <?php if ($figures): ?>
                                    <ul class="ui-rmrp-figures" aria-label="Figures in this memory">
                                        <?php foreach ($figures as $f): ?>
                                            <li><strong><?= htmlspecialchars($f['value']) ?></strong> <?= htmlspecialchars($f['unit']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
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
            <?php elseif ($grandTotal > 0): ?>
                <div class="ui-empty">Nothing matches that. <a href="<?= FULL_BASE_PATH ?>rmrp">Clear filters</a></div>
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
