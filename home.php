<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Settings.php';
require_once __DIR__ . '/includes/ui.php';

$settings = new Settings();
$faviconPath = $settings->getFaviconPath();
$faviconUrl = FULL_BASE_PATH . $faviconPath;

ui_render_head(
    'Yunus Emre Vurgun | Engineer - Designer - Builder',
    'Merging technical resilience with aesthetic purpose.'
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('', true); ?>

    <main class="ui-hero">
        <div class="ui-hero-content">
            <img
                class="ui-hero-portrait"
                src="<?= FULL_BASE_PATH ?>assets/images/yunus-emre-vurgun-portrait.jpg"
                alt="Yunus Emre Vurgun"
                loading="eager"
            >
            <div class="ui-hero-monogram">YEMRE</div>
            <h1 class="ui-hero-name">Yunus Emre Vurgun</h1>
            <p class="ui-hero-tagline">Engineer &middot; Designer &middot; Builder</p>
            <div class="ui-hero-actions">
                <a class="ui-btn ui-btn-primary" href="<?= FULL_BASE_PATH ?>about">About me</a>
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>portfolio">See my work</a>
            </div>
        </div>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
</body>
</html>
