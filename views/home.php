<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

ui_render_head(
    'Yunus Emre Vurgun | SOFTWARE - AGENTS - WIRES',
    'Merging technical resilience with aesthetic purpose.'
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar(''); ?>

    <main class="ui-hero">
        <div class="ui-hero-bg" aria-hidden="true"></div>
        <div class="ui-hero-content">
            <div class="ui-portrait-crossfade ui-hero-portrait-frame">
                <img
                    class="ui-hero-portrait ui-portrait-crossfade-base"
                    src="<?= FULL_BASE_PATH ?>assets/images/yunus-emre-vurgun-portrait.jpg"
                    alt="Yunus Emre Vurgun"
                    loading="eager"
                >
                <img
                    class="ui-hero-portrait ui-portrait-crossfade-alt"
                    src="<?= FULL_BASE_PATH ?>assets/images/real-pfp.png"
                    alt="Yunus Emre Vurgun"
                    loading="eager"
                >
                <img
                    class="ui-hero-portrait ui-portrait-crossfade-tert"
                    src="<?= FULL_BASE_PATH ?>assets/images/portrait-3.png"
                    alt="Yunus Emre Vurgun"
                    loading="eager"
                >
            </div>
            <div class="ui-hero-monogram">YEMRE</div>
            <h1 class="ui-hero-name">Yunus Emre Vurgun</h1>
            <p class="ui-hero-tagline">SOFTWARE &minus; AGENTS &minus; WIRES</p>
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
