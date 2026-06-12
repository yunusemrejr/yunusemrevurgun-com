<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

$travelDataFile = dirname(__DIR__) . '/assets/data/travel-locations.json';
$travelLocations = [];
if (file_exists($travelDataFile)) {
    $jsonContent = file_get_contents($travelDataFile);
    $travelData = json_decode($jsonContent, true);
    if ($travelData && isset($travelData['locations']) && is_array($travelData['locations'])) {
        $travelLocations = $travelData['locations'];
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
        $totalMiles += haversineMilesPhp($hq['lat'], $hq['lng'], $location['lat'], $location['lng']);
    }
}

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
                border: 1px solid var(--color-border, rgba(255,255,255,0.1));
                background: transparent;
                color: var(--color-text-secondary, rgba(255,255,255,0.6));
                cursor: pointer;
                font-size: 0.75rem;
                font-weight: 500;
                font-family: var(--font-mono, ui-monospace, monospace);
                text-transform: uppercase;
                letter-spacing: 0.05em;
                transition: all 0.2s ease;
            }
            .ui-toggle-btn:hover {
                background: rgba(255,255,255,0.05);
                color: var(--color-text-primary, #ffffff);
            }
            .ui-toggle-btn.is-active {
                background: var(--color-text-primary, #EAEAEA);
                color: var(--color-bg-primary, #0D1219);
                border-color: var(--color-text-primary, #EAEAEA);
            }
            .ui-map-view-container {
                position: relative;
                width: 100%;
                height: 65vh;
                min-height: 450px;
                border: 1px solid var(--color-border, rgba(42, 54, 66, 0.7));
                background: var(--color-bg-primary, #0D1219);
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
                background: rgba(0,0,0,0.6);
                backdrop-filter: blur(10px);
                padding: 1rem 1.5rem;
                border: 1px solid rgba(255,255,255,0.1);
            }
            .ui-overlay-stat-item {
                display: flex;
                flex-direction: column;
            }
            .ui-overlay-stat-value {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--color-text-primary, #ffffff);
                font-family: var(--font-mono, ui-monospace, monospace);
            }
            .ui-overlay-stat-label {
                font-size: 0.7rem;
                color: var(--color-text-muted, rgba(255,255,255,0.5));
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
                background: var(--color-text-primary, #ffffff);
                border-radius: 0;
                border: 2px solid var(--color-bg-primary, #0D1219);
                transition: transform 0.2s ease;
            }
            .ui-map-dot:hover .ui-map-dot-core {
                transform: scale(1.5);
            }
            .leaflet-popup-content-wrapper {
                background: var(--color-bg-secondary, #2a2d35) !important;
                color: var(--color-text-primary, #ffffff) !important;
                border-radius: 0 !important;
                border: 1px solid var(--color-border, rgba(255,255,255,0.1));
            }
            .leaflet-popup-tip {
                background: var(--color-bg-secondary, #2a2d35) !important;
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
                border: 1px solid var(--color-border, rgba(255,255,255,0.1));
                background: var(--color-bg-primary, #111);
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
                background: rgba(0,0,0,0.6);
            }
            .ui-travel-modal-panel {
                position: relative;
                background: var(--color-bg-secondary, #2a2d35);
                border: 1px solid var(--color-border, rgba(255,255,255,0.1));
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
                border: 1px solid var(--color-border, rgba(255,255,255,0.1));
                color: var(--color-text-secondary, rgba(255,255,255,0.7));
                cursor: pointer;
                font-size: 1.1rem;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
            }
            .ui-travel-modal-close:hover {
                background: rgba(255,255,255,0.05);
                color: var(--color-text-primary, #ffffff);
            }
            .ui-card-title {
                font-size: 1.25rem;
                font-weight: 600;
                color: var(--color-text-primary, #ffffff);
                margin-bottom: 0.25rem;
                font-family: var(--font-mono, ui-monospace, monospace);
            }
            .ui-card-meta {
                font-size: 0.8rem;
                color: var(--color-text-muted, rgba(255,255,255,0.5));
                font-family: var(--font-mono, ui-monospace, monospace);
                margin-bottom: 1rem;
            }
        </style>'
    ]
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Travel'); ?>

    <main class="ui-main">
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
window.TRAVEL_IMAGE_BASE = <?php echo json_encode(FULL_BASE_PATH . 'assets/images/travel/'); ?>;
</script>
<script data-cfasync="false" src="https://cdnjs.cloudflare.com/ajax/libs/three.js/0.160.0/three.min.js"></script>
<script data-cfasync="false" src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/travel-rebuild.js?v=<?= time() ?>"></script>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
</body>
</html>
