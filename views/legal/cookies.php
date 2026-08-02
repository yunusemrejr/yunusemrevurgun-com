<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';

ui_render_head('Cookie Policy', 'Cookie policy for yunusemrevurgun.com');
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Studio'); ?>
    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Legal</p>
            <h1 class="ui-section-title">Cookie Policy</h1>
            <p class="ui-section-text">Last updated: <?= date('F d, Y') ?></p>
        </section>
        <section class="ui-section">
            <article class="ui-glass-panel ui-rich-content">
                <h2>What We Use Cookies For</h2>
                <p>Cookies support session integrity, CSRF protection, and core site functionality.</p>
                <h2>Cookie Types</h2>
                <p>Essential and security cookies are used for reliable operation; optional third-party CDN resources may set their own cookies.</p>
                <h2>Control</h2>
                <p>You can control cookies through browser settings, though disabling them may affect functionality.</p>
            </article>
        </section>
    </main>
    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
