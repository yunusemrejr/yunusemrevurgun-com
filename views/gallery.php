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
            <p class="ui-section-text">Photos and moments captured over time. Use the arrows or arrow keys to move through the set.</p>
        </section>

        <section class="ui-section">
            <?php if (empty($images)): ?>
                <div class="ui-empty">No gallery images available yet.</div>
            <?php else: ?>
                <div class="ui-slideshow" data-slideshow data-count="<?= count($images) ?>">
                    <div class="ui-slideshow-stage">
                        <button type="button" class="ui-slideshow-arrow ui-slideshow-prev" data-slideshow-prev aria-label="Previous image">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div class="ui-slideshow-viewport">
                            <div class="ui-slideshow-track">
                                <?php foreach ($images as $i => $image):
                                    $imageSrc = FULL_BASE_PATH . 'uploads/gallery/' . ltrim((string)($image['filename'] ?? ''), '/');
                                    $title = (string) ($image['title'] ?? 'Untitled');
                                    ?>
                                    <figure class="ui-slideshow-slide" data-title="<?= htmlspecialchars($title) ?>">
                                        <img class="ui-slideshow-image" src="<?= htmlspecialchars($imageSrc) ?>" alt="<?= htmlspecialchars($title) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                                    </figure>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="button" class="ui-slideshow-arrow ui-slideshow-next" data-slideshow-next aria-label="Next image">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                    <div class="ui-slideshow-meta">
                        <span class="ui-slideshow-counter">
                            <span data-slideshow-current>1</span> / <?= count($images) ?>
                        </span>
                        <span class="ui-slideshow-caption" data-slideshow-caption><?= htmlspecialchars((string)($images[0]['title'] ?? 'Untitled')) ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>

<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
</body>
</html>
