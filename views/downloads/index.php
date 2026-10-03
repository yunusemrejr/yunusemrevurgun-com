<?php
/**
 * Public — Downloads
 * Open-source projects, described from includes/downloads_catalog.php
 * (facts checked against each GitHub repository). Code is hosted on GitHub.
 */
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/views/includes/collection.php';
require_once dirname(__DIR__, 2) . '/views/includes/seo.php';

$catalog = require dirname(__DIR__, 2) . '/includes/downloads_catalog.php';
$coverDir = dirname(__DIR__, 2) . '/assets/images/downloads/';
$list = $catalog; // schema entries; catalog replaces the old admin-managed rows

// Each project has its own page in this section (/downloads/<slug>); the list below
// links to it, and the ItemList points search engines at those pages, not at anchors.
$origin = seo_origin();
$itemList = ['@type' => 'ItemList', '@id' => $origin . '/downloads#projects', 'name' => 'Open-source projects by Yunus Emre Vurgun', 'itemListElement' => array_map(
    static fn(array $app, int $i): array => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $app['title'], 'url' => $origin . '/downloads/' . $app['slug']],
    $list, array_keys($list)
)];

ui_render_head(
    'Open-Source Linux Games and Dev Tools – Free, Source on GitHub',
    'Seven free, open-source Linux projects by Yunus Emre Vurgun: two games, an artificial-life rat, two coding agents, a finance assistant and a disk cleaner.',
    ['downloads' => true, ui_collection_schema('Downloads', 'downloads', array_column($list, 'title'), array_column($list, 'slug')), seo_script([$itemList])]
);
?>
<body class="ui-collection">
<div class="ui-page">
    <?php ui_render_navbar('downloads'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Software</p>
            <h1 class="ui-section-title">Open-source Linux games, tools and coding agents</h1>
            <p class="ui-section-text">Seven free projects I have built, mostly small Linux-native C++: two games, an artificial-life rat, two coding-agent harnesses, a local finance assistant and a disk-cleanup app. Each has its own page with requirements, how to run it and how it works; the code lives on GitHub, so you can clone it, read it and run it locally.</p>
        </section>

        <section class="ui-section">
            <div class="ui-downloads-grid">
                <?php foreach ($catalog as $app):
                    $repoUrl = 'https://github.com/' . $app['repo'];
                    $title = htmlspecialchars($app['title']);
                ?>
                    <article class="ui-card ui-download-card" id="<?= htmlspecialchars($app['slug']) ?>">
                        <div class="ui-download-media">
                            <img class="ui-download-thumb" src="<?= FULL_BASE_PATH ?>assets/images/downloads/<?= htmlspecialchars($app['cover']) ?>?v=<?= filemtime($coverDir . $app['cover']) ?>" alt="<?= htmlspecialchars($app['cover_alt']) ?>" width="1200" height="750" loading="lazy" decoding="async">
                        </div>
                        <div class="ui-download-body">
                            <p class="ui-download-kind"><?= htmlspecialchars($app['kind']) ?></p>
                            <h2 class="ui-card-title"><a href="<?= FULL_BASE_PATH ?>downloads/<?= htmlspecialchars($app['slug']) ?>"><?= $title ?></a></h2>
                            <p class="ui-card-text"><?= htmlspecialchars($app['description']) ?></p>

                            <ul class="ui-tags ui-download-facts">
                                <li class="ui-tag"><?= htmlspecialchars($app['language']) ?></li>
                                <?php if ($app['license']): ?><li class="ui-tag"><?= htmlspecialchars($app['license']) ?> licence</li><?php endif; ?>
                                <?php foreach ($app['platforms'] as $p): ?><li class="ui-tag"><?= htmlspecialchars($p) ?></li><?php endforeach; ?>
                            </ul>

                            <p class="ui-download-section-label">Requires</p>
                            <ul class="ui-download-deps">
                                <?php foreach ($app['requires'] as $dep): ?><li><?= htmlspecialchars($dep) ?></li><?php endforeach; ?>
                            </ul>

                            <p class="ui-download-section-label">Get it</p>
                            <p class="ui-download-how">
                                <?php if ($app['run']): ?>Clone the repository, then run <code><?= htmlspecialchars($app['run']) ?></code>.<?php elseif ($app['release']): ?>Source and a tagged release are on GitHub; the README has the install steps.<?php endif; ?>
                            </p>

                            <div class="ui-download-footer">
                                <a class="ui-btn ui-btn-primary ui-btn-github" href="<?= htmlspecialchars($repoUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $title ?> on GitHub (opens in a new tab)">
                                    <svg class="ui-download-icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82a7.6 7.6 0 0 1 4 0c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg>
                                    View on GitHub ↗
                                </a>
                                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>downloads/<?= htmlspecialchars($app['slug']) ?>" aria-label="<?= $title ?>: requirements, how to run it and how it works">Details</a>
                                <?php if ($app['release']): ?>
                                    <a class="ui-btn ui-btn-secondary" href="<?= htmlspecialchars($app['release']['url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($app['release']['label']) ?> <?= htmlspecialchars($app['release']['tag']) ?> ↗</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="ui-section">
            <?php ui_render_ebook_aside('downloads'); ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>

<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>

</body>
</html>
