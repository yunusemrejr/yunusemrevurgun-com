<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Portfolio.php';
require_once __DIR__ . '/includes/ui.php';

$portfolio = new Portfolio();
$projects = $portfolio->getAllProjects();
$years = $portfolio->getDistinctYears();
$categories = $portfolio->getDistinctCategories();

ui_render_head(
    'Work | Project Archive',
    'Chronological showcase of development work across AI/ML, operational technology, and systems architecture.'
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Work'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Archive</p>
            <h1 class="ui-section-title">Project Archive</h1>
            <p class="ui-section-text">Chronological showcase of work spanning AI/ML systems, operational technology, and architecture projects.</p>
        </section>

        <section class="ui-section">
            <div class="ui-archive-filters" role="toolbar" aria-label="Project filters">
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
                        ?>
                        <article class="ui-project-card" data-year="<?= htmlspecialchars($year) ?>" data-featured="<?= $isFeatured ?>" data-tags="<?= htmlspecialchars($tagsRaw) ?>" data-category="<?= htmlspecialchars($project['category'] ?? '') ?>">
                            <?php if (!empty($project['image'])): ?>
                                <img class="ui-media" src="<?= FULL_BASE_PATH . htmlspecialchars($project['image']) ?>" alt="<?= htmlspecialchars($project['title']) ?>">
                            <?php endif; ?>
                            <h2 class="ui-project-title"><?= htmlspecialchars($project['title']) ?></h2>
                            <?php if (!empty($year)): ?>
                                <p class="ui-card-meta" style="padding: 0 1.25rem;"><?= htmlspecialchars($year) ?></p>
                            <?php endif; ?>
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
                                <div style="padding: 0 1.25rem 1.25rem;">
                                    <?php if (!empty($project['project_url'])): ?>
                                        <a class="ui-case-link" href="<?= htmlspecialchars($project['project_url']) ?>" target="_blank" rel="noopener noreferrer">View Project →</a>
                                    <?php else: ?>
                                        <a class="ui-case-link" href="<?= htmlspecialchars($project['github_url']) ?>" target="_blank" rel="noopener noreferrer">View on GitHub →</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
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
