<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

http_response_code(404);
ui_render_head('404 | Page Not Found', 'The page you requested was not found.', ['robots' => 'noindex,follow']);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar(); ?>
    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">404</p>
            <h1 class="ui-section-title">Page Not Found</h1>
            <p class="ui-section-text">The page you requested does not exist or was moved. Everything on this site is reachable from here.</p>
        </section>

        <section class="ui-section">
            <label class="ui-field-label" for="f404q">Search the site</label>
            <form class="ui-search-form" action="<?= FULL_BASE_PATH ?>search" method="get" role="search">
                <input class="ui-input" id="f404q" type="text" name="q" placeholder="Projects, journal entries, updates..." required>
                <button class="ui-btn ui-btn-secondary" type="submit">Search</button>
            </form>
            <div class="ui-more-grid">
                <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>">
                    <span class="ui-more-icon" aria-hidden="true">←</span>
                    <h2 class="ui-more-title">Home</h2>
                    <p class="ui-more-desc">Who this is and what the site holds.</p>
                </a>
                <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>portfolio">
                    <span class="ui-more-icon" aria-hidden="true">[]</span>
                    <h2 class="ui-more-title">Project archive</h2>
                    <p class="ui-more-desc">AI/ML, operational technology, industrial automation, web infrastructure.</p>
                </a>
                <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>blog">
                    <span class="ui-more-icon" aria-hidden="true">¶</span>
                    <h2 class="ui-more-title">Journal</h2>
                    <p class="ui-more-desc">Long-form notes on AI, systems, and post-code engineering.</p>
                </a>
                <a class="ui-more-card" href="<?= FULL_BASE_PATH ?>more">
                    <span class="ui-more-icon" aria-hidden="true">+</span>
                    <h2 class="ui-more-title">Everything else</h2>
                    <p class="ui-more-desc">YunoBot, travel, gallery, music, videos, downloads, comedy, science corner.</p>
                </a>
            </div>
            <?php ui_render_ebook_line('missing'); ?>
        </section>
    </main>
    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
