<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

ui_render_head(
    'Comedy | Yunus Emre Vurgun',
    'Shower Thoughts & Memes — @showerthoughtsamp',
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('more'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Comedy</p>
            <h1 class="ui-section-title">Shower Thoughts &amp; Memes</h1>
            <p class="ui-section-text">Random thoughts, absurd observations, and internet humor from @showerthoughtsamp.</p>
        </section>

        <!-- Socials -->
        <section class="ui-section">
            <h2 class="ui-section-subtitle">Socials</h2>
            <div class="comedy-socials">
                <a class="comedy-social-card" href="https://www.instagram.com/showerthoughtsamp" target="_blank" rel="noopener">
                    <span class="comedy-social-icon" aria-hidden="true">📸</span>
                    <div>
                        <span class="comedy-social-name">Instagram</span>
                        <span class="comedy-social-handle">@showerthoughtsamp</span>
                    </div>
                </a>
                <a class="comedy-social-card" href="https://www.tiktok.com/@showerthoughtsamp" target="_blank" rel="noopener">
                    <span class="comedy-social-icon" aria-hidden="true">🎵</span>
                    <div>
                        <span class="comedy-social-name">TikTok</span>
                        <span class="comedy-social-handle">@showerthoughtsamp</span>
                    </div>
                </a>
            </div>
        </section>

        <!-- Memes -->
        <section class="ui-section">
            <h2 class="ui-section-subtitle">Memes</h2>
            <div class="comedy-memes">
                <div class="tenor-gif-embed" data-postid="10218248190964058871" data-share-method="host" data-aspect-ratio="1" data-width="100%"></div>
                <div class="tenor-gif-embed" data-postid="22423735" data-share-method="host" data-aspect-ratio="1.25" data-width="100%"></div>
                <div class="tenor-gif-embed" data-postid="4485592100516537029" data-share-method="host" data-aspect-ratio="0.761044" data-width="100%"></div>
                <div class="tenor-gif-embed" data-postid="4871515084258742720" data-share-method="host" data-aspect-ratio="1.05508" data-width="100%"></div>
                <div class="tenor-gif-embed" data-postid="14578253283354428711" data-share-method="host" data-aspect-ratio="0.98996" data-width="100%"></div>
                <div class="tenor-gif-embed" data-postid="7328295092409424487" data-share-method="host" data-aspect-ratio="0.803213" data-width="100%"></div>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<script type="text/javascript" async src="https://tenor.com/embed.js"></script>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
</body>
</html>
