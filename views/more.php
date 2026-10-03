<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Socials.php';
require_once __DIR__ . '/includes/ui.php';

$morePages = [
    [
        'title' => 'YunoBot',
        'href' => FULL_BASE_PATH . 'yunobot',
        'description' => 'Ask about my work or find a passage in the journal, with links to the source.',
        'icon' => '>',
    ],
    [
        'title' => 'Claim Splitter',
        'href' => FULL_BASE_PATH . 'gemmaclaim',
        'description' => 'Paste a claim and a 270M model splits it into premises and assumptions, in your browser.',
        'icon' => '▚',
    ],
    [
        'title' => 'Chessko',
        'href' => FULL_BASE_PATH . 'chessko',
        'description' => 'Jelly chess in your browser: a home-grown engine, or Stockfish running as WebAssembly. Plus how it all works.',
        'icon' => '♞',
    ],
    [
        'title' => 'Jello Shop',
        'href' => FULL_BASE_PATH . 'jelloshop',
        'description' => 'A cosy hand-drawn coffee shop. Walk Jell-omo, a 3D jelly in a cap, around the room, pick up coffee beans, pet the cat.',
        // A drawn mug, not an emoji: it takes the colour of the icon badge.
        'icon_svg' => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M4.5 10h11v4.6a4.9 4.9 0 0 1-4.9 4.9h-1.2a4.9 4.9 0 0 1-4.9-4.9z"/><path d="M15.5 11.4h1.3a2.6 2.6 0 0 1 0 5.2h-1.3"/><path d="M8.6 3.4c-1.2 1.1 1.2 2.1 0 3.5M12 3.4c-1.2 1.1 1.2 2.1 0 3.5"/><path d="M3.5 21.6h13"/></svg>',
        'icon' => 'JS',
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
        'description' => 'Figures, equations, and ideas from physics, mathematics and computer science.',
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
    [
        'title' => 'Quality 3D AI Slop',
        'href' => FULL_BASE_PATH . 'slop',
        'description' => 'Single-file HTML pages with 3D things in them.',
        'icon' => '◈',
    ],
    [
        'title' => 'The Book',
        'href' => EBOOK_URL,
        'description' => 'How to Remain Valuable When Intelligence Becomes Cheap — the 224-page ebook on staying valuable as AI advances. On Gumroad ↗',
        'icon' => '§',
        'external' => true,
    ],
];



ui_render_head(
    'Explore | Yunus Emre Vurgun’s Projects, Media & Interests',
    'Projects you can run in your browser, open-source downloads, media, socials and interests by Yunus Emre Vurgun (Yemre), from YunoBot to Chessko.',
);
?>
<body class="ui-collection">
<div class="ui-page">
    <?php ui_render_navbar('more'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Explore</p>
            <h1 class="ui-section-title">Explore</h1>
            <p class="ui-section-text">The other parts of this site: things I make, listen to, study, and keep.</p>
        </section>

        <section class="ui-section">
            <div class="ui-more-grid">
                <?php foreach ($morePages as $page): ?>
                    <a class="ui-more-card" href="<?= htmlspecialchars($page['href']) ?>"<?= !empty($page['external']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                        <span class="ui-more-icon" aria-hidden="true"><?= $page['icon_svg'] ?? htmlspecialchars($page['icon']) ?></span>
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

    <?php ui_render_footer('footer-right'); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>