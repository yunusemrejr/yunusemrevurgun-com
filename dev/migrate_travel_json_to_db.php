<?php
/**
 * One-time migration: import assets/data/travel-locations.json into the DB
 * (travel_locations + travel_location_images).
 *
 * Idempotent: does nothing when travel_locations already has rows.
 * CLI only — safe to keep in the deployed tree (403 via web).
 *
 * Usage: php dev/migrate_travel_json_to_db.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Travel.php';

$jsonFile = dirname(__DIR__) . '/assets/data/travel-locations.json';
if (!is_file($jsonFile)) {
    fwrite(STDERR, "JSON file not found: $jsonFile\n");
    exit(1);
}

$data = json_decode((string)file_get_contents($jsonFile), true);
$locations = $data['locations'] ?? null;
if (!is_array($locations) || count($locations) === 0) {
    fwrite(STDERR, "No locations found in JSON\n");
    exit(1);
}

// Instantiating the model creates the tables (driver-branched DDL).
new Travel();
$db = Database::getInstance()->getConnection();

$existing = (int)$db->query('SELECT COUNT(*) FROM travel_locations')->fetchColumn();
if ($existing > 0) {
    echo "travel_locations already has $existing rows — nothing to do.\n";
    exit(0);
}

$insLoc = $db->prepare(
    'INSERT INTO travel_locations (country, city, lat, lng, visited, sort_order)
     VALUES (:country, :city, :lat, :lng, :visited, :sort_order)'
);
$insImg = $db->prepare(
    'INSERT INTO travel_location_images (location_id, filename, title, sort_order)
     VALUES (:location_id, :filename, :title, :sort_order)'
);

$locCount = 0;
$imgCount = 0;

$db->beginTransaction();
try {
    foreach ($locations as $i => $loc) {
        $country = trim((string)($loc['country'] ?? ''));
        $city = trim((string)($loc['city'] ?? ''));
        $lat = $loc['lat'] ?? null;
        $lng = $loc['lng'] ?? null;

        if ($country === '' || $city === '' || !is_numeric($lat) || !is_numeric($lng)
            || abs((float)$lat) > 90 || abs((float)$lng) > 180) {
            fwrite(STDERR, 'Skipping invalid entry #' . ($i + 1) . "\n");
            continue;
        }

        $visited = trim((string)($loc['visited'] ?? ''));
        $insLoc->execute([
            ':country' => $country,
            ':city' => $city,
            ':lat' => (float)$lat,
            ':lng' => (float)$lng,
            ':visited' => $visited !== '' ? $visited : null,
            ':sort_order' => 0,
        ]);
        $locationId = (int)$db->lastInsertId();
        $locCount++;

        $images = $loc['images'] ?? [];
        if (is_array($images)) {
            foreach ($images as $j => $filename) {
                $filename = trim((string)$filename);
                if ($filename === '') {
                    continue;
                }
                $insImg->execute([
                    ':location_id' => $locationId,
                    ':filename' => $filename,
                    ':title' => 'Untitled',
                    ':sort_order' => $j,
                ]);
                $imgCount++;
            }
        }
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'Migration failed, rolled back: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Imported $locCount locations and $imgCount images.\n";
exit(0);
