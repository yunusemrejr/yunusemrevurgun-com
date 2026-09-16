<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

// Load travel data from database
require_once dirname(__DIR__) . '/models/Travel.php';
$travelModel = new Travel();
$dbLocations = $travelModel->getAllLocations();

// Build the locations array in the format the JS expects
$travelLocations = [];
$imagesByLocation = $travelModel->getImagesGroupedByLocation();
foreach ($dbLocations as $loc) {
    $images = $imagesByLocation[$loc['id']] ?? [];
    $imageFilenames = [];
    foreach ($images as $img) {
        $imageFilenames[] = $img['filename'];
    }

    $travelLocations[] = [
        'id' => (int)$loc['id'],
        'country' => $loc['country'],
        'city' => $loc['city'],
        'lat' => (float)$loc['lat'],
        'lng' => (float)$loc['lng'],
        'images' => $imageFilenames,
        'visited' => $loc['visited'] ?? ''
    ];
}

// Fallback: if DB is empty, try reading from JSON file (migration safety net)
if (empty($travelLocations)) {
    $travelDataFile = dirname(__DIR__) . '/assets/data/travel-locations.json';
    if (file_exists($travelDataFile)) {
        $jsonContent = file_get_contents($travelDataFile);
        $travelData = json_decode($jsonContent, true);
        if ($travelData && isset($travelData['locations']) && is_array($travelData['locations'])) {
            $travelLocations = $travelData['locations'];
        }
    }
}

$uniqueCountries = [];
$totalMiles = 0;
$hq = ['lat' => 41.0082, 'lng' => 28.9784];

function haversineMilesPhp($lat1, $lon1, $lat2, $lon2) {
    $r = 3958.8;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $r * $c;
}

foreach ($travelLocations as $location) {
    if (!empty($location['country'])) {
        $uniqueCountries[$location['country']] = true;
    }
    if (!empty($location['lat']) && !empty($location['lng'])) {
        $totalMiles += haversineMilesPhp($hq['lat'], $hq['lng'], (float)$location['lat'], (float)$location['lng']);
    }
}

// Image base path: uploaded images go to uploads/travel/, existing static images are in assets/images/travel/
// Gallery-referenced images are in uploads/gallery/
$imageBase = FULL_BASE_PATH . 'uploads/travel/';
$staticImageBase = FULL_BASE_PATH . 'assets/images/travel/';
$galleryImageBase = FULL_BASE_PATH . 'uploads/gallery/';

ui_render_head(
    'Travel | Yunus Emre Vurgun',
    'Travel map and statistics from Istanbul headquarters.',
    [
        '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">',
        '<style>
            .ui-travel-toggle {
                display: flex;
                gap: 0.5rem;
                margin-bottom: 1.5rem;
                justify-content: center;
            }
            .ui-toggle-btn {
                padding: 0.5rem 1.25rem;
                border-radius: 0;
                border: 1px solid var(--color-border, var(--color-border));
                background: transparent;
                color: var(--color-text-secondary, var(--color-accent-secondary));
                cursor: pointer;
                font-size: 0.75rem;
                font-weight: 500;
                font-family: var(--font-mono, ui-monospace, monospace);
                text-transform: uppercase;
                letter-spacing: 0.05em;
                transition: all 0.2s ease;
            }
            .ui-toggle-btn:hover {
                background: var(--color-bg-subtle);
                color: var(--color-ink-body, var(--color-ink-body));
            }
            .ui-toggle-btn.is-active {
                background: var(--color-text-primary, var(--color-text-tertiary));
                color: var(--color-bg-primary, var(--color-bg-primary));
                border-color: var(--color-text-primary, var(--color-text-tertiary));
            }
            .ui-map-view-container {
                position: relative;
                width: 100%;
                height: 65vh;
                min-height: 450px;
                border: 1px solid var(--color-border, var(--color-border));
                background: var(--color-bg-primary, var(--color-bg-primary));
            }
            .ui-map-view {
                position: absolute;
                inset: 0;
                transition: opacity 0.4s ease, transform 0.4s ease;
                z-index: 1;
            }
            .ui-map-view.is-hidden {
                opacity: 0;
                pointer-events: none;
                transform: scale(1.02);
                z-index: 0;
            }
            #travelGlobeCanvas {
                width: 100%;
                height: 100%;
                cursor: grab;
            }
            #travelGlobeCanvas:active {
                cursor: grabbing;
            }
            .ui-map-overlay-stats {
                position: absolute;
                bottom: 1.5rem;
                left: 1.5rem;
                z-index: 10;
                display: flex;
                gap: 2rem;
                background: rgba(227,226,222,0.85);
                backdrop-filter: blur(10px);
                padding: 1rem 1.5rem;
                border: 1px solid var(--color-border-strong, var(--color-border-strong));
            }
            .ui-overlay-stat-item {
                display: flex;
                flex-direction: column;
            }
            .ui-overlay-stat-value {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--color-ink-body);
                font-family: var(--font-mono, ui-monospace, monospace);
            }
            .ui-overlay-stat-label {
                font-size: 0.7rem;
                color: var(--color-text-secondary, var(--color-accent-secondary));
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-family: var(--font-mono, ui-monospace, monospace);
            }
            .ui-map-dot {
                background: transparent;
                border: none;
            }
            .ui-map-dot-core {
                display: block;
                width: 10px;
                height: 10px;
                background: var(--color-accent);
                border-radius: 0;
                border: 2px solid var(--color-bg-primary, var(--color-bg-primary));
                transition: transform 0.2s ease;
            }
            .ui-map-dot:hover .ui-map-dot-core {
                transform: scale(1.5);
            }
            .leaflet-popup-content-wrapper {
                background: var(--color-bg-elevated, #edeceb) !important;
                color: var(--color-ink-body) !important;
                border-radius: 0 !important;
                border: 1px solid var(--color-border, var(--color-border));
            }
            .leaflet-popup-tip {
                background: var(--color-bg-elevated, #edeceb) !important;
            }
            .ui-travel-modal-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1rem;
                margin-top: 1.5rem;
            }
            .ui-travel-image-wrap {
                aspect-ratio: 4/3;
                border-radius: 0;
                overflow: hidden;
                border: 1px solid var(--color-border, var(--color-border));
                background: var(--color-bg-card, #f5f4f1);
            }
            .ui-travel-image-wrap img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.3s ease;
            }
            .ui-travel-image-wrap:hover img {
                transform: scale(1.05);
            }
            @media (max-width: 768px) {
                .ui-map-view-container {
                    height: 50vh;
                }
                .ui-map-overlay-stats {
                    bottom: 1rem;
                    left: 1rem;
                    right: 1rem;
                    gap: 1rem;
                    justify-content: space-around;
                }
            }
            .ui-travel-modal {
                display: none;
                position: fixed;
                inset: 0;
                z-index: 1000;
                align-items: center;
                justify-content: center;
            }
            .ui-travel-modal[aria-hidden="false"] { display: flex; }
            .ui-travel-modal-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(227,226,222,0.7);
            }
            .ui-travel-modal-panel {
                position: relative;
                background: var(--color-bg-elevated, #edeceb);
                border: 1px solid var(--color-border-strong, var(--color-border-strong));
                padding: 2rem;
                max-width: 560px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
            }
            .ui-travel-modal-close {
                position: absolute;
                top: 1rem;
                right: 1rem;
                width: 28px;
                height: 28px;
                background: transparent;
                border: 1px solid var(--color-border, var(--color-border));
                color: var(--color-text-secondary, var(--color-accent-secondary));
                cursor: pointer;
                font-size: 1.1rem;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
            }
            .ui-travel-modal-close:hover {
                background: var(--color-bg-subtle);
                color: var(--color-ink-body);
            }
            .ui-card-title {
                font-size: 1.25rem;
                font-weight: 600;
                color: var(--color-ink-body);
                margin-bottom: 0.25rem;
                font-family: var(--font-mono, ui-monospace, monospace);
            }
            .ui-card-meta {
                font-size: 0.8rem;
                color: var(--color-text-secondary, var(--color-accent-secondary));
                font-family: var(--font-mono, ui-monospace, monospace);
                margin-bottom: 1rem;
            }

            /* Lightbox styles */
            .ui-travel-lightbox {
                display: none;
                position: fixed;
                inset: 0;
                z-index: 2000;
                align-items: center;
                justify-content: center;
            }
            .ui-travel-lightbox-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(0,0,0,0.85);
            }
            .ui-travel-lightbox-panel {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                max-width: 90vw;
                max-height: 90vh;
                width: auto;
                height: auto;
            }
            .ui-travel-lightbox-image-wrap {
                display: flex;
                align-items: center;
                justify-content: center;
                max-width: 85vw;
                max-height: 85vh;
            }
            .ui-travel-lightbox-image-wrap img {
                max-width: 85vw;
                max-height: 85vh;
                object-fit: contain;
                box-shadow: 0 4px 40px rgba(0,0,0,0.4);
            }
            .ui-travel-lightbox-close {
                position: fixed;
                top: 1rem;
                right: 1rem;
                width: 40px;
                height: 40px;
                background: rgba(0,0,0,0.5);
                border: none;
                color: #fff;
                font-size: 1.5rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 2001;
                transition: background 0.2s ease;
            }
            .ui-travel-lightbox-close:hover {
                background: rgba(0,0,0,0.8);
            }
            .ui-travel-lightbox-nav {
                position: fixed;
                top: 50%;
                transform: translateY(-50%);
                width: 48px;
                height: 48px;
                background: rgba(0,0,0,0.4);
                border: none;
                color: #fff;
                font-size: 2rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 2001;
                transition: background 0.2s ease;
            }
            .ui-travel-lightbox-nav:hover {
                background: rgba(0,0,0,0.7);
            }
            .ui-travel-lightbox-prev { left: 1rem; }
            .ui-travel-lightbox-next { right: 1rem; }
            .ui-travel-lightbox-nav span {
                line-height: 1;
                margin-top: -2px;
            }
            .ui-travel-lightbox-counter {
                position: fixed;
                bottom: 1.5rem;
                left: 50%;
                transform: translateX(-50%);
                color: rgba(255,255,255,0.7);
                font-size: 0.85rem;
                font-family: var(--font-mono, ui-monospace, monospace);
                z-index: 2001;
                background: rgba(0,0,0,0.4);
                padding: 0.35rem 0.75rem;
            }
            .ui-travel-image-wrap {
                cursor: pointer;
            }
        </style>'
    ]
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Travel'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Travel Log</p>
            <h1 class="ui-section-title">World Exploration</h1>
            <p class="ui-section-text">A log of locations visited from Istanbul HQ, documenting journeys through coordinate mapping.</p>
        </section>

        <section class="ui-section">
            <?php if (empty($travelLocations)): ?>
                <div class="ui-empty">No travel locations available.</div>
            <?php else: ?>
                <div class="ui-travel-toggle">
                    <button class="ui-toggle-btn is-active" data-view-toggle="2d">2D MAP</button>
                    <button class="ui-toggle-btn" data-view-toggle="3d">3D GLOBE</button>
                </div>

                <div class="ui-map-view-container">
                    <div id="travelMap" class="ui-map-view" aria-label="2D Travel Map"></div>
                    <div id="travelGlobe" class="ui-map-view is-hidden" aria-label="3D Travel Globe">
                        <div id="travelGlobeCanvas"></div>
                    </div>

                    <div class="ui-map-overlay-stats">
                        <div class="ui-overlay-stat-item">
                            <span class="ui-overlay-stat-value" id="travelCountries"><?= count($uniqueCountries) ?></span>
                            <span class="ui-overlay-stat-label">Countries</span>
                        </div>
                        <div class="ui-overlay-stat-item">
                            <span class="ui-overlay-stat-value" id="travelMiles"><?= number_format($totalMiles) ?></span>
                            <span class="ui-overlay-stat-label">Miles Traveled</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>

<div class="ui-travel-modal" id="travelImageModal" aria-hidden="true">
    <div class="ui-travel-modal-backdrop" data-travel-modal-close></div>
    <div class="ui-travel-modal-panel">
        <button type="button" class="ui-travel-modal-close" data-travel-modal-close aria-label="Close">×</button>
        <h2 class="ui-card-title" id="travelModalTitle">Location</h2>
        <p class="ui-card-meta" id="travelModalMeta"></p>
        <div class="ui-travel-modal-grid" id="travelModalGrid"></div>
    </div>
</div>

<script data-cfasync="false">
window.TRAVEL_LOCATIONS = <?php echo json_encode($travelLocations, JSON_UNESCAPED_UNICODE); ?>;
window.TRAVEL_IMAGE_BASE = <?php echo json_encode($imageBase); ?>;
window.TRAVEL_STATIC_IMAGE_BASE = <?php echo json_encode($staticImageBase); ?>;
window.TRAVEL_GALLERY_IMAGE_BASE = <?php echo json_encode($galleryImageBase); ?>;
</script>
<script data-cfasync="false" src="https://cdnjs.cloudflare.com/ajax/libs/three.js/0.160.0/three.min.js"></script>
<script data-cfasync="false" src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/travel-rebuild.js?v=<?= filemtime(realpath(__DIR__ . '/../assets/js/travel-rebuild.js') ?: __DIR__ . '/../assets/js/travel-rebuild.js') ?>"></script>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
