<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

ui_render_head('Sitemap — Yunus Emre Vurgun', 'Structured links for all primary routes on yunusemrevurgun.com.');
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar(); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Navigation</p>
            <h1 class="ui-section-title">Sitemap</h1>
            <p class="ui-section-text">Direct access to all core pages and legal routes.</p>
        </section>

        <section class="ui-section">
            <div class="ui-sitemap-cols">
                <div class="ui-sitemap-col">
                    <h2>Main</h2>
                    <ul class="ui-card-list">
                        <li><a href="<?= FULL_BASE_PATH ?>">Home</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>about">About</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>portfolio">Portfolio</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>blog">Blog</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>contact">Contact</a></li>
                    </ul>
                </div>
                <div class="ui-sitemap-col">
                    <h2>Archive</h2>
                    <ul class="ui-card-list">
                        <li><a href="<?= FULL_BASE_PATH ?>gallery">Gallery</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>updates">Updates</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>rmrp">Random Memories</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>travel">Travel</a></li>
                    </ul>
                </div>
                <div class="ui-sitemap-col">
                    <h2>Explore</h2>
                    <ul class="ui-card-list">
                        <?php foreach (['yunobot' => 'YunoBot', 'music' => 'Music', 'videos' => 'Videos', 'downloads' => 'Downloads', 'slop' => 'Quality 3D AI Slop', 'post-code' => 'Post-Code', 'science-corner' => 'Science Corner', 'comedy' => 'Comedy', 'more' => 'More'] as $path => $label): ?>
                        <li><a href="<?= FULL_BASE_PATH . $path ?>"><?= $label ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="ui-sitemap-col">
                    <h2>Legal</h2>
                    <ul class="ui-card-list">
                        <li><a href="<?= FULL_BASE_PATH ?>privacy">Privacy Policy</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>terms">Terms of Use</a></li>
                        <li><a href="<?= FULL_BASE_PATH ?>cookies">Cookie Policy</a></li>
                    </ul>
                </div>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
