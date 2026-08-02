<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

$morePages = [
    [
        'title' => 'YunoBot',
        'href' => FULL_BASE_PATH . 'yunobot',
        'description' => 'Terminal-style AI chat interface powered by local ML models.',
        'icon' => '>',
    ],
    [
        'title' => 'Post-Code',
        'href' => FULL_BASE_PATH . 'post-code',
        'description' => 'Concepts and mathematics foundations for post-code era computing.',
        'icon' => '//',
    ],
    [
        'title' => 'Science Corner',
        'href' => FULL_BASE_PATH . 'science-corner',
        'description' => 'Quotes, equations, and ideas from the greatest scientific minds.',
        'icon' => 'Σ',
    ],
    [
        'title' => 'Music',
        'href' => FULL_BASE_PATH . 'music',
        'description' => 'Audio tracks with an in-browser music player.',
        'icon' => '♪',
    ],
    [
        'title' => 'Comedy',
        'href' => FULL_BASE_PATH . 'comedy',
        'description' => 'Shower thoughts and memes from @showerthoughtsamp.',
        'icon' => '☺',
    ],
    [
        'title' => 'Videos',
        'href' => FULL_BASE_PATH . 'videos',
        'description' => 'Video uploads, YouTube embeds, and Odysee clips.',
        'icon' => '▶',
    ],
    [
        'title' => 'Downloads',
        'href' => FULL_BASE_PATH . 'downloads',
        'description' => 'Desktop and offline apps from my personal projects, hosted on GitHub.',
        'icon' => '↓',
    ],
];

$currentPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');

ui_render_head(
    'More | Yunus Emre Vurgun',
    'Additional projects and pages.',
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('more'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Explore</p>
            <h1 class="ui-section-title">More</h1>
            <p class="ui-section-text">Additional projects and experiments alongside the main work.</p>
        </section>

        <section class="ui-section">
            <div class="ui-more-grid">
                <?php foreach ($morePages as $page): ?>
                    <a class="ui-more-card" href="<?= htmlspecialchars($page['href']) ?>">
                        <span class="ui-more-icon" aria-hidden="true"><?= htmlspecialchars($page['icon']) ?></span>
                        <h2 class="ui-more-title"><?= htmlspecialchars($page['title']) ?></h2>
                        <p class="ui-more-desc"><?= htmlspecialchars($page['description']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>