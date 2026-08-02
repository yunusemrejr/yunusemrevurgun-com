<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

http_response_code(404);
ui_render_head('404 | Page Not Found', 'The page you requested was not found.');
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar(); ?>
    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">404</p>
            <h1 class="ui-section-title">Page Not Found</h1>
            <p class="ui-section-text">The page you requested does not exist or was moved.</p>
            <div style="margin-top: 2rem;">
                <a class="ui-btn ui-btn-primary" href="<?= FULL_BASE_PATH ?>">Back to Home</a>
            </div>
        </section>
    </main>
    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
