<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Settings.php';
require_once __DIR__ . '/includes/ui.php';

$settings = new Settings();

ui_render_head(
    'Yemre | The world of a developer',
    'A new form of intelligence is emerging. — Yemre, the world of a developer.',
    [
        '<meta property="og:title" content="Yemre | The world of a developer">',
        '<meta property="og:description" content="A new form of intelligence is emerging. They say you\'re the average of the five people you spend the most time with. I\'m carefully curating mine.">',
        '<meta property="og:type" content="website">',
        '<meta property="og:url" content="' . htmlspecialchars(rtrim(FULL_BASE_PATH, '/') . '/') . '">',
        '<meta name="author" content="Yemre">',
        '<meta name="keywords" content="Yemre, developer, software, agents, intelligence, Yunus Emre Vurgun">',
    ]
);
?>
<body class="ui-landing">
<div class="ui-landing-svg">
    <!-- Desktop SVG hero (navigation, portrait, background, headings, decorative elements) -->
    <img class="ui-landing-svg-desktop" src="<?= FULL_BASE_PATH ?>assets/images/desktop.svg" alt="Yemre — The world of a developer" loading="eager" draggable="false">
    <!-- Mobile SVG hero -->
    <img class="ui-landing-svg-mobile" src="<?= FULL_BASE_PATH ?>assets/images/mobile.svg" alt="Yemre — The world of a developer" loading="eager" draggable="false">
</div>

<div class="ui-landing-actions">
    <a class="ui-landing-btn ui-landing-btn-primary" href="<?= FULL_BASE_PATH ?>about">About me</a>
    <a class="ui-landing-btn ui-landing-btn-secondary" href="<?= FULL_BASE_PATH ?>portfolio">See my work</a>
</div>

<footer class="ui-landing-footer">
    <div class="ui-landing-footer-inner">
        <nav class="ui-landing-footer-nav" aria-label="Footer navigation">
            <ul role="list">
                <?php foreach (ui_nav_items() as $item): ?>
                <li><a href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?= FULL_BASE_PATH ?>more">+</a></li>
            </ul>
        </nav>
        <ul class="ui-landing-footer-socials" role="list">
            <li><a href="https://github.com/yunusemrejr" target="_blank" rel="noopener noreferrer">GitHub</a></li>
            <li><a href="https://www.youtube.com/@yunusemrevurgun1" target="_blank" rel="noopener noreferrer">YouTube</a></li>
            <li><a href="https://linkedin.com/in/yunus-emre-vurgun-49ba9a177" target="_blank" rel="noopener noreferrer">LinkedIn</a></li>
            <li><a href="https://mastodon.social/@yunusemrevurgn" target="_blank" rel="noopener noreferrer">Mastodon</a></li>
            <li><a href="https://bsky.app/profile/yunusemrevurgun.bsky.social" target="_blank" rel="noopener noreferrer">Bluesky</a></li>
            <li><a href="https://www.threads.com/@yemrevu" target="_blank" rel="noopener noreferrer">Threads</a></li>
            <li><a href="https://instagram.com/yemrevu" target="_blank" rel="noopener noreferrer">Instagram</a></li>
            <li><a href="https://x.com/yemrevu" target="_blank" rel="noopener noreferrer">X / Twitter</a></li>
        </ul>
        <p class="ui-landing-footer-copy">© <?= date('Y') ?> Yemre. All rights reserved.</p>
    </div>
</footer>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<script src="<?= FULL_BASE_PATH ?>assets/js/ui-interactions.js?v=<?= filemtime(__DIR__ . '/../assets/js/ui-interactions.js') ?>"></script>
</body>
</html>