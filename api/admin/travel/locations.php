<?php
/**
 * Travel Locations API
 * Handles CRUD operations for travel locations
 */

error_reporting(E_ALL);
ini_set('display_errors', getenv('MODE') === 'development' ? '1' : '0');

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__) && 
    (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

require_once dirname(__DIR__, 3) . '/config/setPath.php';
require_once dirname(__DIR__, 3) . '/global.php';
require_once dirname(__DIR__, 3) . '/models/Auth.php';
require_once dirname(__DIR__, 3) . '/models/Travel.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';

header('Content-Type: application/json');

if (!Auth::checkLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $travel = new Travel();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $country = trim($_POST['country'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $lat = $_POST['lat'] ?? '';
            $lng = $_POST['lng'] ?? '';
            $visited = $_POST['visited'] ?? null;

            if (empty($country) || empty($city) || empty($lat) || empty($lng)) {
                throw new Exception('Country, city, latitude, and longitude are required');
            }
            if (!is_numeric($lat) || !is_numeric($lng) || abs((float)$lat) > 90 || abs((float)$lng) > 180) {
                throw new Exception('Latitude/longitude must be numeric (lat -90..90, lng -180..180)');
            }

            $id = $travel->addLocation($country, $city, (float)$lat, (float)$lng, $visited ?: null);

            echo json_encode([
                'success' => true,
                'message' => 'Location added successfully',
                'location_id' => $id
            ]);
            break;

        case 'update':
            $id = $_POST['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                throw new Exception('Invalid location ID');
            }

            $data = [];
            if (isset($_POST['country'])) {
                $country = trim($_POST['country']);
                if (empty($country)) throw new Exception('Country cannot be empty');
                $data['country'] = $country;
            }
            if (isset($_POST['city'])) {
                $city = trim($_POST['city']);
                if (empty($city)) throw new Exception('City cannot be empty');
                $data['city'] = $city;
            }
            if (isset($_POST['lat'])) {
                if (!is_numeric($_POST['lat']) || abs((float)$_POST['lat']) > 90) throw new Exception('Latitude must be numeric (-90..90)');
                $data['lat'] = (float)$_POST['lat'];
            }
            if (isset($_POST['lng'])) {
                if (!is_numeric($_POST['lng']) || abs((float)$_POST['lng']) > 180) throw new Exception('Longitude must be numeric (-180..180)');
                $data['lng'] = (float)$_POST['lng'];
            }
            if (isset($_POST['visited'])) $data['visited'] = $_POST['visited'] ?: null;

            if (empty($data)) throw new Exception('No data to update');

            $result = $travel->updateLocation((int)$id, $data);

            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Location updated successfully' : 'Failed to update location'
            ]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                throw new Exception('Invalid location ID');
            }

            $result = $travel->deleteLocation((int)$id);

            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Location deleted successfully' : 'Failed to delete location'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Operation failed: ' . $e->getMessage()
    ]);
}
?>
