<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Gallery.php';
require_once __DIR__ . '/includes/ui.php';

$gallery = new Gallery();
$images = $gallery->getAllImages(false);

ui_render_head(
    'Experiments | Gallery',
    'Visual archive of projects, processes, and technical explorations.'
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Gallery'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Archive</p>
            <h1 class="ui-section-title">Gallery</h1>
            <p class="ui-section-text">Photos and moments captured over time. Hover to reveal color.</p>
        </section>

        <section class="ui-section">
            <?php if (empty($images)): ?>
                <div class="ui-empty">No gallery images available yet.</div>
            <?php else: ?>
                <div class="ui-gallery-grid">
                    <?php foreach ($images as $image):
                        $imageSrc = FULL_BASE_PATH . 'uploads/gallery/' . ltrim((string)($image['filename'] ?? ''), '/');
                        $title = (string) ($image['title'] ?? 'Untitled');
                        ?>
                        <article class="ui-gallery-item" data-image="<?= htmlspecialchars($imageSrc) ?>" data-title="<?= htmlspecialchars($title) ?>">
                            <img class="ui-gallery-image" src="<?= htmlspecialchars($imageSrc) ?>" alt="<?= htmlspecialchars($title) ?>" loading="lazy">
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>

<div class="ui-lightbox" id="uiLightbox" aria-hidden="true">
    <div class="ui-lightbox-backdrop" data-lightbox-close></div>
    <div class="ui-lightbox-content">
        <button type="button" class="ui-lightbox-close" data-lightbox-close aria-label="Close">×</button>
        <img class="ui-lightbox-image" src="" alt="">
    </div>
</div>

<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
</body>
</html>
