<?php
/**
 * Public — Downloads
 * Lists desktop/offline apps. Binaries are hosted externally (GitHub); this
 * page only renders metadata + thumbnails managed in the admin panel.
 */
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/models/Downloads.php';

$downloads = new Downloads();
$list = $downloads->getAllDownloads();
$thumbBase = FULL_BASE_PATH . Downloads::UPLOAD_DIR . '/';

ui_render_head(
    'Downloads | Yunus Emre Vurgun',
    'Desktop and offline apps I have built — free downloads, hosted on GitHub.',
    ['downloads' => true]
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('downloads'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Software</p>
            <h1 class="ui-section-title">Downloads</h1>
            <p class="ui-section-text">Desktop and offline apps from my personal projects. Everything is free and hosted on GitHub — grab a release and run it locally.</p>
        </section>

        <section class="ui-section">
            <?php if (count($list) > 0): ?>
                <div class="ui-downloads-grid">
                    <?php foreach ($list as $item):
                        $platforms = Downloads::decodeList($item['platforms'] ?? null);
                        $dependencies = Downloads::decodeList($item['dependencies'] ?? null);
                        $title = htmlspecialchars($item['title'] ?? 'Untitled');
                    ?>
                        <article class="ui-card ui-download-card">
                            <div class="ui-download-media">
                                <?php if (!empty($item['thumbnail'])): ?>
                                    <img class="ui-download-thumb" src="<?= htmlspecialchars($thumbBase . $item['thumbnail']) ?>" alt="<?= $title ?> thumbnail" loading="lazy">
                                <?php else: ?>
                                    <div class="ui-download-placeholder" aria-hidden="true">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="ui-download-body">
                                <h2 class="ui-card-title"><?= $title ?></h2>
                                <?php if (!empty($item['description'])): ?>
                                    <p class="ui-card-text"><?= htmlspecialchars($item['description']) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($platforms)): ?>
                                    <p class="ui-download-section-label">Platforms</p>
                                    <div class="ui-tags" style="margin-bottom:0;">
                                        <?php foreach ($platforms as $p): ?>
                                            <span class="ui-tag"><?= htmlspecialchars($p) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($dependencies)): ?>
                                    <p class="ui-download-section-label">Requires</p>
                                    <ul class="ui-download-deps">
                                        <?php foreach ($dependencies as $dep): ?>
                                            <li><?= htmlspecialchars($dep) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <div class="ui-download-footer">
                                    <a class="ui-btn ui-btn-primary" href="<?= htmlspecialchars($item['download_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer">
                                        <svg class="ui-download-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        Download
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ui-downloads-empty">
                    <p>No downloads available yet. Check back soon.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>

<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
</body>
</html>
