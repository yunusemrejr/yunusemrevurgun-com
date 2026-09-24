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

// Travel presentation lives in the shared design system
// (assets/css/ui-rebuild.css, TRAVEL PAGE section) — no page-level styles.
ui_render_head(
    'Travel | Yunus Emre Vurgun',
    'Travel map and statistics from Istanbul headquarters.',
    [
        '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">',
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
                <dl class="ui-travel-stats-strip">
                    <div class="ui-travel-stat">
                        <dt class="ui-travel-stat-label">Countries</dt>
                        <dd class="ui-travel-stat-value" id="travelCountries"><?= count($uniqueCountries) ?></dd>
                    </div>
                    <div class="ui-travel-stat">
                        <dt class="ui-travel-stat-label">Miles traveled</dt>
                        <dd class="ui-travel-stat-value" id="travelMiles"><?= number_format($totalMiles) ?></dd>
                    </div>
                </dl>

                <div class="ui-map-view-container">
                    <div id="travelMap" role="application" aria-label="Travel map of visited locations"></div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer('footer-left'); ?>
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
<script data-cfasync="false" src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/travel-rebuild.js?v=<?= filemtime(realpath(__DIR__ . '/../assets/js/travel-rebuild.js') ?: __DIR__ . '/../assets/js/travel-rebuild.js') ?>"></script>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>

</body>
</html>
