<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Portfolio.php';
require_once __DIR__ . '/includes/ui.php';

$portfolio = new Portfolio();
$projects = $portfolio->getAllProjects();
$years = []; $categories = [];
foreach ($projects as $project) {
    $date = $project['completion_date'] ?: ($project['created_at'] ?? '');
    if ($date && strtotime($date)) $years[] = date('Y', strtotime($date));
    if (!empty($project['category'])) $categories[] = $project['category'];
}
$years = array_unique($years); rsort($years);
$categories = array_unique($categories); sort($categories);

require_once __DIR__ . '/includes/collection.php';
// Typed project entries (invisible): the same facts the cards show —
// description, technologies, completion date, live/source links — as
// CreativeWork nodes engines can attribute and date.
$projectItems = [];
foreach ($projects as $projectRow) {
    $work = [
        '@type' => 'CreativeWork',
        'name' => (string)$projectRow['title'],
        'author' => ['@id' => rtrim(FULL_BASE_PATH, '/') . '/#person'],
        'inLanguage' => 'en-US',
    ];
    if (!empty($projectRow['description'])) $work['description'] = seo_description((string)$projectRow['description'], 300);
    if (!empty($projectRow['technologies'])) $work['keywords'] = (string)$projectRow['technologies'];
    if (!empty($projectRow['category'])) $work['genre'] = (string)$projectRow['category'];
    $doneAt = $projectRow['completion_date'] ?: ($projectRow['created_at'] ?? null);
    if (!empty($doneAt)) $work['dateCreated'] = date('Y-m-d', (int)strtotime((string)$doneAt));
    if (!empty($projectRow['image'])) $work['image'] = seo_asset_url((string)$projectRow['image']);
    $sameAs = array_values(array_filter([(string)($projectRow['project_url'] ?? ''), (string)($projectRow['github_url'] ?? '')]));
    if ($sameAs !== []) $work['sameAs'] = $sameAs;
    $projectItems[] = ['anchor' => 'project-' . (int)$projectRow['id'], 'item' => $work];
}
$portfolioMeta = $projectItems !== [] ? [seo_collection_graph('Portfolio', 'portfolio', $projectItems)] : [];
$portfolioFresh = seo_newest_stamp($projects, ['updated_at', 'created_at']);
if ($portfolioFresh !== null) $portfolioMeta['date_modified'] = $portfolioFresh;

ui_render_head(
    'Software Developer Portfolio – AI/ML, OT and Web Projects',
    'Project archive by Yunus Emre Vurgun (Yemre): AI/ML, operational technology, industrial automation and web projects, each with the technologies it uses.',
    $portfolioMeta
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Work'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Archive</p>
            <h1 class="ui-section-title">Software developer portfolio: project archive</h1>
            <p class="ui-section-text">Chronological showcase of work spanning AI/ML systems, operational technology, and architecture projects.</p>
        </section>

        <section class="ui-section">
            <div class="ui-archive-filters" role="group" aria-label="Project filters">
                <button class="ui-filter-pill is-active" type="button" data-filter="all">All</button>
                <button class="ui-filter-pill" type="button" data-filter="featured">Featured</button>
                <?php foreach ($years as $year): ?>
                    <button class="ui-filter-pill" type="button" data-filter="y<?= $year ?>"><?= $year ?></button>
                <?php endforeach; ?>
                <?php foreach ($categories as $cat): ?>
                    <button class="ui-filter-pill" type="button" data-filter="c:<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                <?php endforeach; ?>
            </div>

            <?php if (empty($projects)): ?>
                <div class="ui-empty" style="margin-top: 1rem;">No projects published yet.</div>
            <?php else: ?>
                <div class="ui-project-grid">
                    <?php foreach ($projects as $project):
                        $year = '';
                        if (!empty($project['completion_date'])) {
                            $year = date('Y', strtotime((string) $project['completion_date']));
                        } elseif (!empty($project['created_at'])) {
                            $year = date('Y', strtotime((string) $project['created_at']));
                        }
                        $tagsRaw = (string) ($project['technologies'] ?? '');
                        $isFeatured = !empty($project['featured']) ? '1' : '0';
                        // Thumbnails are cropped to 4:3 by convention; an image far from that (a 1.9:1 social
                        // card, a tall phone shot) is shown whole on the light table instead of being cut.
                        $shotFit = '';
                        if (!empty($project['image'])) {
                            $shotFile = dirname(__DIR__) . '/' . ltrim((string)$project['image'], '/');
                            $shotSize = !str_contains((string)$project['image'], '..') && is_file($shotFile) ? @getimagesize($shotFile) : false;
                            if (is_array($shotSize) && $shotSize[1] > 0 && abs(($shotSize[0] / $shotSize[1]) / (4 / 3) - 1) > 0.15) $shotFit = ' is-fit';
                        }
                        ?>
                        <article id="project-<?= (int)$project['id'] ?>" class="ui-project-card" data-year="<?= htmlspecialchars($year) ?>" data-featured="<?= $isFeatured ?>" data-tags="<?= htmlspecialchars($tagsRaw) ?>" data-category="<?= htmlspecialchars($project['category'] ?? '') ?>">
                            <?php if (!empty($project['image'])): ?>
                                <div class="ui-project-shot<?= $shotFit ?>">
                                    <img class="ui-media" loading="lazy" decoding="async" src="<?= FULL_BASE_PATH . htmlspecialchars($project['image']) ?>"<?= ui_image_size_attrs($project['image']) ?> alt="<?= htmlspecialchars($project['title']) ?>">
                                </div>
                            <?php endif; ?>
                            <div class="ui-project-body">
                                <?php if ($isFeatured === '1' || $year !== '' || !empty($project['category'])): ?>
                                    <p class="ui-project-meta">
                                        <?php if ($isFeatured === '1'): ?><span class="ui-project-flag">Featured</span><?php endif; ?>
                                        <?php if ($year !== ''): ?><span><?= htmlspecialchars($year) ?></span><?php endif; ?>
                                        <?php if (!empty($project['category'])): ?><span><?= htmlspecialchars($project['category']) ?></span><?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <h2 class="ui-project-title"><a href="#project-<?= (int)$project['id'] ?>"><?= htmlspecialchars($project['title']) ?></a></h2>
                                <?php if (!empty($project['description'])): ?>
                                    <p class="ui-project-desc"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                                <?php endif; ?>

                                <?php
                                $techItems = array_filter(array_map('trim', explode(',', $tagsRaw)));
                                if (!empty($techItems)): ?>
                                    <div class="ui-project-stack">
                                        <?php foreach ($techItems as $tech): ?>
                                            <span class="ui-tech-tag"><?= htmlspecialchars($tech) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($project['project_url']) || !empty($project['github_url'])): ?>
                                    <div class="ui-project-links">
                                        <?php if (!empty($project['project_url'])): ?>
                                            <a class="ui-case-link" href="<?= htmlspecialchars($project['project_url']) ?>" target="_blank" rel="noopener noreferrer">View Project →</a>
                                        <?php else: ?>
                                            <a class="ui-case-link" href="<?= htmlspecialchars($project['github_url']) ?>" target="_blank" rel="noopener noreferrer">View on GitHub →</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
