<?php
/**
 * Portfolio projects added from the repository instead of the admin form.
 *
 * Portfolio::applySeeds() inserts each entry once, on the first web request
 * after a deploy, and records its key in `portfolio_seeds`. A project deleted
 * or edited in the admin panel afterwards stays that way. `image_source` is a
 * tracked file; it is copied into uploads/portfolio/ like an admin upload,
 * because deleting a project unlinks its image.
 */
return [
    'paperoom' => [
        'title' => 'Paperoom',
        'description' => 'Free printable games for gatherings, classrooms and family tables. Make custom word or 75-ball bingo, 90-ball tickets, word searches with answer keys, and editable prompt cards in English, Spanish, Arabic, Brazilian Portuguese, Simplified Chinese and French. All game content is processed in the browser, with direct A4 and Letter PDF downloads, large-print layouts, offline PWA support and separate optional analytics.',
        'image_source' => 'assets/images/portfolio/paperoom.png',
        'project_url' => 'https://paperoom.yunusemrevurgun.com/',
        'github_url' => '',
        'technologies' => 'JavaScript, HTML, CSS, SVG',
        'category' => 'Web Development',
        'completion_date' => '2026-10-29',
        'featured' => 0,
    ],
    'kiwibro' => [
        'title' => 'Kiwibro',
        'description' => 'A local-first AI agent for Linux that runs on LM Studio. Chat with any model on your machine, keep a global memory you can edit, and let it search the web, edit files or run commands inside its own workspace, with your permission. Python and Flask behind a GNOME-style browser window; no cloud, no accounts, no telemetry. Fully open source (MIT).',
        'image_source' => 'assets/images/portfolio/kiwibro.webp',
        'project_url' => '',
        'github_url' => 'https://github.com/yunusemrejr/kiwibro',
        'technologies' => 'Python, Flask, JavaScript, SQLite',
        'category' => 'AI/ML',
        'completion_date' => '2026-10-21',
        'featured' => 1,
    ],
    'flyingkiwi' => [
        'title' => 'Flyingkiwi',
        'description' => 'An experimental English and Turkish AI chat project with a simple, elegant interface. Two language-specific local models and browser-based WASM/WebGPU deployment are in development. The current public preview does not generate replies.',
        'image_source' => '',
        'project_url' => 'https://flyingkiwi.yunusemrevurgun.com/',
        'github_url' => '',
        'technologies' => 'JavaScript, Python, WASM/WebGPU research',
        'category' => 'AI/ML',
        'completion_date' => null,
        'featured' => 0,
    ],
];
