<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';

ui_render_head('Privacy Policy', 'Privacy policy for yunusemrevurgun.com');
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Studio'); ?>
    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Legal</p>
            <h1 class="ui-section-title">Privacy Policy</h1>
            <p class="ui-section-text">Last updated: <?= date('F d, Y') ?></p>
        </section>
        <section class="ui-section">
            <article class="ui-glass-panel ui-rich-content">
                <h2>Information We Collect</h2>
                <p>We collect technical usage data (IP, browser/device details, timestamps, referrer) and contact form submissions (name, email, message).</p>
                <h2>How We Use Data</h2>
                <p>Data is used to operate and secure the site, improve performance, and respond to inquiries.</p>
                <h2>Your Rights</h2>
                <p>You may request access, correction, or deletion of your personal data through the contact page.</p>
            </article>
        </section>
    </main>
    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>

</body>
</html>
