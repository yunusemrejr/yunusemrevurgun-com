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

// Structured data for the photographs themselves (invisible): one
// ImageObject per visible image so image search can index them with their
// titles, sizes and upload dates instead of guessing from filenames.
$galleryImages = $gallery->getAllImages(false);
$galleryItems = [];
foreach ($galleryImages as $galleryImage) {
    if (empty($galleryImage['filename'])) continue;
    $galleryItems[] = ['anchor' => null, 'item' => seo_image_object(
        'uploads/gallery/' . ltrim((string)$galleryImage['filename'], '/'),
        (string)($galleryImage['title'] ?? ''),
        isset($galleryImage['uploaded_at']) ? (string)$galleryImage['uploaded_at'] : null
    )];
}
$galleryMeta = $galleryItems !== [] ? [seo_collection_graph('Gallery', 'gallery', $galleryItems)] : [];
$galleryFresh = seo_newest_stamp($galleryImages, ['uploaded_at']);
if ($galleryFresh !== null) $galleryMeta['date_modified'] = $galleryFresh;

// One slideshow per set. "Untitled" is the upload default, not a caption, so it is not shown; the
// alt text then names the set and the position instead.
$renderSlideshow = static function (array $set, string $setName): void {
    $total = count($set);
    $captionOf = static function (array $image): string {
        $title = trim((string)($image['title'] ?? ''));
        return strcasecmp($title, 'Untitled') === 0 ? '' : $title;
    };
    ?>
                    <div class="ui-slideshow" data-slideshow data-count="<?= $total ?>">
                        <div class="ui-slideshow-stage">
                            <button type="button" class="ui-slideshow-arrow ui-slideshow-prev" data-slideshow-prev aria-label="Previous image">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div class="ui-slideshow-viewport">
                                <div class="ui-slideshow-track">
                                    <?php foreach (array_values($set) as $i => $image):
                                        $file = 'uploads/gallery/' . ltrim((string)($image['filename'] ?? ''), '/');
                                        $caption = $captionOf($image);
                                        $alt = $caption !== '' ? $caption : $setName . ', photo ' . ($i + 1) . ' of ' . $total;
                                        ?>
                                        <figure class="ui-slideshow-slide" data-title="<?= htmlspecialchars($caption) ?>">
                                            <img class="ui-slideshow-image" src="<?= htmlspecialchars(FULL_BASE_PATH . $file) ?>"<?= ui_image_size_attrs($file) ?> alt="<?= htmlspecialchars($alt) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async">
                                        </figure>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button type="button" class="ui-slideshow-arrow ui-slideshow-next" data-slideshow-next aria-label="Next image">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </div>
                        <div class="ui-slideshow-meta">
                            <span class="ui-slideshow-counter"><span data-slideshow-current>1</span><span class="ui-slideshow-of">/ <?= $total ?></span></span>
                            <span class="ui-slideshow-caption" data-slideshow-caption><?= htmlspecialchars($captionOf($set[array_key_first($set)] ?? [])) ?></span>
                        </div>
                    </div>
    <?php
};

ui_render_head(
    'Gallery — Experiments | Yunus Emre Vurgun, Developer',
    'Visual archive by Yunus Emre Vurgun — projects, processes, and technical explorations.',
    $galleryMeta
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Gallery'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <noscript><style>
            /* Without JavaScript the sets degrade to plain stacked figures. */
            .ui-slideshow-track { display: block; }
            .ui-slideshow-slide { display: block; margin-bottom: var(--space-4); }
            .ui-slideshow-arrow, .ui-slideshow-meta { display: none; }
        </style></noscript>
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
                    <?php $renderSlideshow($images, 'Gallery'); ?>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <!-- Album-based grouping -->
            <?php foreach ($albumData as $album): ?>
                <?php if ($album['image_count'] > 0): ?>
                <section class="ui-section">
                    <div class="ui-album-header">
                        <h2 class="ui-album-title"><?= htmlspecialchars($album['name']) ?></h2>
                        <span class="ui-album-count"><?= (int)$album['image_count'] ?> <?= (int)$album['image_count'] === 1 ? 'photo' : 'photos' ?></span>
                        <?php if (!empty($album['description'])): ?>
                        <p class="ui-album-description"><?= htmlspecialchars($album['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php $renderSlideshow($album['images'], (string)$album['name']); ?>
                </section>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <?php ui_render_footer(); ?>
</div>

<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
