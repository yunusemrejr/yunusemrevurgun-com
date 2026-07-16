<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

if (!isset($GLOBALS['current_update'])) {
    header('Location: ' . FULL_BASE_PATH . 'updates');
    exit;
}

$update = $GLOBALS['current_update'];

ui_render_head(
    ($update['title'] ?? 'Update') . ' | Updates',
    mb_substr(strip_tags((string)($update['description'] ?? '')), 0, 150),
    [
        '<meta property="og:type" content="article">',
        '<meta property="og:title" content="' . htmlspecialchars($update['title'] ?? 'Update', ENT_QUOTES) . '">',
        '<script type="application/ld+json">' . json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => (string)($update['title'] ?? 'Update'),
            'datePublished' => date('c', strtotime($update['update_date'] ?? $update['created_at'] ?? 'now')),
            'dateModified' => date('c', strtotime($update['updated_at'] ?? $update['created_at'] ?? 'now')),
            'author' => ['@type' => 'Person', 'name' => 'Yunus Emre Vurgun'],
            'mainEntityOfPage' => rtrim(FULL_BASE_PATH, '/') . '/updates/' . (int)($update['id'] ?? 0),
            'description' => (string)mb_substr(strip_tags((string)($update['description'] ?? '')), 0, 220),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>'
    ]
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Journal'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <div class="ui-update-row" style="margin-bottom: 1.5rem;">
                <img
                    class="ui-update-avatar"
                    src="<?= FULL_BASE_PATH ?>assets/images/favicon.svg"
                    alt="Yunus Emre Vurgun"
                    loading="eager"
                >
                <div class="ui-update-body">
                    <p class="ui-eyebrow" style="margin-bottom: 0.25rem;">Updates</p>
                    <h1 class="ui-section-title" style="margin-bottom: 0.25rem;"><?= htmlspecialchars($update['title']) ?></h1>
                    <p class="ui-section-text"><?= date('F j, Y H:i', strtotime($update['update_date'] ?? $update['created_at'] ?? 'now')) ?></p>
                </div>
            </div>
        </section>

        <section class="ui-section">
            <article class="ui-glass-panel">
                <?php if (!empty($update['category']) || !empty($update['importance'])): ?>
                    <div class="ui-tags" style="margin-bottom: 1rem;">
                        <?php if (!empty($update['category'])): ?><span class="ui-tag"><?= htmlspecialchars($update['category']) ?></span><?php endif; ?>
                        <?php if (!empty($update['importance'])): ?><span class="ui-tag"><?= htmlspecialchars(ucfirst($update['importance'])) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="ui-rich-content"><?= $update['description'] ?></div>
            </article>
            <div class="ui-tags" style="margin-top: 1.5rem;">
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>updates">← Back to Updates</a>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
</body>
</html>
