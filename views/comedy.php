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
                    <svg class="comedy-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791-4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    <div>
                        <span class="comedy-social-name">Instagram</span>
                        <span class="comedy-social-handle">@showerthoughtsamp</span>
                    </div>
                </a>
                <a class="comedy-social-card" href="https://www.tiktok.com/@showerthoughtsamp" target="_blank" rel="noopener">
                    <svg class="comedy-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93v6.16c0 2.52-1.12 4.84-2.9 6.24-1.72 1.36-4.01 1.9-6.15 1.45-2.2-.46-4.12-1.9-5.08-3.95-.96-2.05-.77-4.52.5-6.39 1.27-1.87 3.44-3.03 5.69-3.03.71 0 1.41.09 2.08.27v4.16c-.6-.37-1.3-.57-2.02-.57-1.27 0-2.44.68-3.08 1.78-.64 1.1-.64 2.47 0 3.57.64 1.1 1.81 1.78 3.08 1.78 1.27 0 2.44-.68 3.08-1.78.32-.55.49-1.18.49-1.82V.02h.23z"/></svg>
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
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
