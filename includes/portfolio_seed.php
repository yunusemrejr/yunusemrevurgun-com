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
];
