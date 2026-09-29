<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

$message = 'The site is temporarily unavailable. Please check back shortly.';
http_response_code(503);
header('Retry-After: 3600');

ui_render_head('Site Maintenance', 'Temporary maintenance mode.');
?>
<body>
<div class="ui-page">
    <main class="ui-main" style="display:flex; align-items:center; min-height:100vh; justify-content:center;">
        <section class="ui-glass-panel" style="max-width: 600px; width: 100%; text-align:center;">
            <img class="ui-mascot-note-img" src="<?= FULL_BASE_PATH ?>assets/images/mascot-160.webp" width="140" height="140" alt="" style="margin:-4px auto 0;filter:drop-shadow(0 14px 12px rgba(27,33,64,.25));transform:rotate(-8deg);">
            <p class="ui-eyebrow">Maintenance</p>
            <h1 class="ui-section-title" style="max-width:none; margin-top: 0.5rem;">Site Temporarily Unavailable</h1>
            <p class="ui-section-text" style="margin-top: 1rem;"><?= htmlspecialchars((string) $message) ?></p>
            <div style="margin-top: 2rem;">
                <a class="ui-btn ui-btn-secondary" href="<?= FULL_BASE_PATH ?>admin/login">Admin Login</a>
            </div>
        </section>
    </main>
</div>

</body>
</html>
