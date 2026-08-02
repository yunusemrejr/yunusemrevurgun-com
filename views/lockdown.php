<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

$message = '';

ui_render_head('Site Maintenance', 'Temporary maintenance mode.');
?>
<body>
<div class="ui-page">
    <main class="ui-main" style="display:flex; align-items:center; min-height:100vh; justify-content:center;">
        <section class="ui-glass-panel" style="max-width: 600px; width: 100%; text-align:center;">
            <p class="ui-eyebrow">Maintenance</p>
            <h1 class="ui-section-title" style="max-width:none; margin-top: 0.5rem;">Site Temporarily Unavailable</h1>
            <p class="ui-section-text" style="margin-top: 1rem;"><?= htmlspecialchars((string) $message) ?></p>
            <div style="margin-top: 2rem;">
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>admin/login">Admin Login</a>
            </div>
        </section>
    </main>
</div>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
