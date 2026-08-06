<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Gallery.php';
require_once __DIR__ . '/includes/ui.php';

$gallery = new Gallery();

// Get albums with their images
$albumData = $gallery->getAlbumsWithImages();

// Filter out empty albums (except for backward compatibility check)
$hasAlbums = false;
$hasUnassigned = false;
foreach ($albumData as $album) {
    if (isset($album['is_unassigned'])) {
        if ($album['image_count'] > 0) {
            $hasUnassigned = true;
        }
    } else {
        if ($album['image_count'] > 0) {
            $hasAlbums = true;
        }
    }
}

// If no albums exist at all, fall back to single slideshow (backward compatible)
$useSingleSlideshow = !$hasAlbums && !$hasUnassigned;
if ($useSingleSlideshow) {
    $images = $gallery->getAllImages(false);
}

ui_render_head(
    'Gallery — Experiments | Yunus Emre Vurgun, Developer',
    'Visual archive by Yunus Emre Vurgun — projects, processes, and technical explorations.'
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

        <?php if ($useSingleSlideshow): ?>
            <!-- Backward compatible: single slideshow when no albums exist -->
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
        <?php else: ?>
            <!-- Album-based grouping -->
            <?php foreach ($albumData as $album): ?>
                <?php if ($album['image_count'] > 0): ?>
                <section class="ui-section">
                    <div class="ui-album-header">
                        <h2 class="ui-album-title"><?= htmlspecialchars($album['name']) ?></h2>
                        <?php if (!empty($album['description'])): ?>
                        <p class="ui-album-description"><?= htmlspecialchars($album['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ui-slideshow" data-slideshow data-count="<?= $album['image_count'] ?>">
                        <div class="ui-slideshow-stage">
                            <button type="button" class="ui-slideshow-arrow ui-slideshow-prev" data-slideshow-prev aria-label="Previous image">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div class="ui-slideshow-viewport">
                                <div class="ui-slideshow-track">
                                    <?php foreach ($album['images'] as $i => $image):
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
                                <span data-slideshow-current>1</span> / <?= $album['image_count'] ?>
                            </span>
                            <span class="ui-slideshow-caption" data-slideshow-caption><?= htmlspecialchars((string)($album['images'][0]['title'] ?? 'Untitled')) ?></span>
                        </div>
                    </div>
                </section>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <?php ui_render_footer(); ?>
</div>

<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
