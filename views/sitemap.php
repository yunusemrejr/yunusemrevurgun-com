<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

ui_render_head('Sitemap — Yunus Emre Vurgun', 'Structured links for all primary routes on yunusemrevurgun.com.');
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar(); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Navigation</p>
            <h1 class="ui-section-title">Sitemap</h1>
            <p class="ui-section-text">Direct access to all core pages and legal routes.</p>
        </section>

        <section class="ui-section">
            <div class="ui-grid">
                <article class="ui-card">
                    <h2 class="ui-card-title">Main</h2>
                    <ul class="ui-card-list" style="margin-top: 0.75rem;">
                        <li><a href="<?= FULL_BASE_PATH ?>home">Home</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>about">About</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>portfolio">Portfolio</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>blog">Blog</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>contact">Contact</a></li>
                    </ul>
                </article>
                <article class="ui-card">
                    <h2 class="ui-card-title">Archive</h2>
                    <ul class="ui-card-list" style="margin-top: 0.75rem;">
                        <li><a href="<?= FULL_BASE_PATH ?>gallery">Gallery</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>updates">Updates</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>rmrp">Random Memories</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>travel">Travel</a></li>
                    </ul>
                </article>
                <article class="ui-card">
                    <h2 class="ui-card-title">Legal</h2>
                    <ul class="ui-card-list" style="margin-top: 0.75rem;">
                        <li><a href="<?= FULL_BASE_PATH ?>privacy">Privacy Policy</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>terms">Terms of Use</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>cookies">Cookie Policy</a></li>
                    </ul>
                </article>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
