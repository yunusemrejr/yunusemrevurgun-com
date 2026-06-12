<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';

ui_render_head('Terms of Use', 'Terms for using yunusemrevurgun.com');
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Studio'); ?>
    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Legal</p>
            <h1 class="ui-section-title">Terms of Use</h1>
            <p class="ui-section-text">Last updated: <?= date('F d, Y') ?></p>
        </section>
        <section class="ui-section">
            <article class="ui-glass-panel ui-rich-content">
                <h2>Agreement</h2>
                <p>By using this website, you agree to these terms and applicable laws.</p>
                <h2>Intellectual Property</h2>
                <p>Site content, media, and code are protected and may not be reused without permission.</p>
                <h2>Limitations</h2>
                <p>This site is provided as-is. The owner is not liable for indirect damages from site use.</p>
            </article>
        </section>
    </main>
    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
</body>
</html>
