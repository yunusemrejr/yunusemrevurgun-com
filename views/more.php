<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Socials.php';
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
        'title' => 'Random Memories',
        'href' => FULL_BASE_PATH . 'rmrp',
        'description' => 'Fragments, fleeting thoughts, and small moments worth keeping — random memories for random people.',
        'icon' => '✳',
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
    'More — Yunus Emre Vurgun | Developer',
    'Additional projects and pages by Yunus Emre Vurgun (Yemre) — socials, tools, and experiments.',
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

        <?php
        $allSocials = array_values(array_filter(
            (new Socials())->getActive(),
            fn($s) => $s['kind'] === Socials::KIND_LINK && !empty($s['url'])
        ));
        ?>
        <?php if (!empty($allSocials)): ?>
        <section class="ui-section">
            <p class="ui-eyebrow">Socials</p>
            <h2 class="ui-section-title">Socials</h2>
            <p class="ui-section-text">Where to find me around the internet.</p>
            <ul class="ui-socials-list" role="list">
                <?php foreach ($allSocials as $s): ?>
                <li class="ui-socials-item">
                    <a class="ui-socials-link" href="<?= htmlspecialchars($s['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer">
                        <?= Socials::iconSvg($s['icon'] ?? 'link', 'ui-socials-icon') ?>
                        <span class="ui-socials-name"><?= htmlspecialchars($s['name'] ?? '', ENT_QUOTES) ?></span>
                        <span class="ui-socials-handle"><?= htmlspecialchars(($s['handle'] ?? '') !== '' ? $s['handle'] : ($s['name'] ?? ''), ENT_QUOTES) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>