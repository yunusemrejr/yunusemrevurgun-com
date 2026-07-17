<?php
require_once __DIR__ . '/../config/setPath.php';

require_once __DIR__ . '/Database.php';

class Travel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTablesIfNotExists();
    }

    private function createTablesIfNotExists() {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $locationQuery = "CREATE TABLE IF NOT EXISTS travel_locations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                country VARCHAR(255) NOT NULL,
                city VARCHAR(255) NOT NULL,
                lat REAL NOT NULL,
                lng REAL NOT NULL,
                visited DATE NULL,
                sort_order INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $imageQuery = "CREATE TABLE IF NOT EXISTS travel_location_images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                location_id INTEGER NOT NULL,
                filename VARCHAR(255) NOT NULL,
                title VARCHAR(255),
                source VARCHAR(20) DEFAULT 'upload',
                gallery_image_id INTEGER DEFAULT NULL,
                sort_order INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (location_id) REFERENCES travel_locations(id)
            )";
        } else {
            $locationQuery = "CREATE TABLE IF NOT EXISTS travel_locations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                country VARCHAR(255) NOT NULL,
                city VARCHAR(255) NOT NULL,
                lat DECIMAL(10,6) NOT NULL,
                lng DECIMAL(10,6) NOT NULL,
                visited DATE NULL,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $imageQuery = "CREATE TABLE IF NOT EXISTS travel_location_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                location_id INT NOT NULL,
                filename VARCHAR(255) NOT NULL,
                title VARCHAR(255),
                source VARCHAR(20) DEFAULT 'upload',
                gallery_image_id INT DEFAULT NULL,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (location_id) REFERENCES travel_locations(id)
            )";
        }

        $this->db->exec($locationQuery);
        $this->db->exec($imageQuery);
    }

    // ==================== LOCATION METHODS ====================

    public function getAllLocations() {
        $query = "SELECT * FROM travel_locations ORDER BY sort_order ASC, country ASC, city ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLocationById($id) {
        $query = "SELECT * FROM travel_locations WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addLocation($country, $city, $lat, $lng, $visited = null) {
        requireAdminSession(false);

        $query = "INSERT INTO travel_locations (country, city, lat, lng, visited, sort_order) VALUES (:country, :city, :lat, :lng, :visited, :sort_order)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':country', $country, PDO::PARAM_STR);
        $stmt->bindValue(':city', $city, PDO::PARAM_STR);
        $stmt->bindValue(':lat', $lat, PDO::PARAM_STR);
        $stmt->bindValue(':lng', $lng, PDO::PARAM_STR);
        $stmt->bindValue(':visited', $visited, $visited ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':sort_order', 0, PDO::PARAM_INT);
        $stmt->execute();
        return $this->db->lastInsertId();
    }

    public function updateLocation($id, $data) {
        requireAdminSession(false);

        $fields = [];
        $params = [':id' => $id];
        if (isset($data['country'])) {
            $fields[] = 'country = :country';
            $params[':country'] = $data['country'];
        }
        if (isset($data['city'])) {
            $fields[] = 'city = :city';
            $params[':city'] = $data['city'];
        }
        if (isset($data['lat'])) {
            $fields[] = 'lat = :lat';
            $params[':lat'] = $data['lat'];
        }
        if (isset($data['lng'])) {
            $fields[] = 'lng = :lng';
            $params[':lng'] = $data['lng'];
        }
        if (isset($data['visited'])) {
            $fields[] = 'visited = :visited';
            $params[':visited'] = $data['visited'] ?: null;
        }
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = :sort_order';
            $params[':sort_order'] = $data['sort_order'];
        }
        if (empty($fields)) return false;

        $query = "UPDATE travel_locations SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function deleteLocation($id) {
        requireAdminSession(false);

        // Delete associated images from DB first (files handled by caller)
        $query = "DELETE FROM travel_location_images WHERE location_id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $query = "DELETE FROM travel_locations WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    public function getTotalLocations() {
        $query = "SELECT COUNT(*) FROM travel_locations";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getTotalCountries() {
        $query = "SELECT COUNT(DISTINCT country) FROM travel_locations";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // ==================== IMAGE METHODS ====================

    private function migrateSourceColumn() {
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $columns = [];
                $result = $this->db->query("PRAGMA table_info(travel_location_images)");
                foreach ($result as $row) {
                    $columns[$row['name']] = $row;
                }
                if (!isset($columns['source'])) {
                    $this->db->exec("ALTER TABLE travel_location_images ADD COLUMN source TEXT DEFAULT 'upload'");
                }
                if (!isset($columns['gallery_image_id'])) {
                    $this->db->exec("ALTER TABLE travel_location_images ADD COLUMN gallery_image_id INTEGER DEFAULT NULL");
                }
            } else {
                try {
                    $this->db->exec("ALTER TABLE travel_location_images ADD COLUMN source VARCHAR(20) DEFAULT 'upload'");
                } catch (PDOException $e) {}
                try {
                    $this->db->exec("ALTER TABLE travel_location_images ADD COLUMN gallery_image_id INT DEFAULT NULL");
                } catch (PDOException $e) {}
            }
        } catch (PDOException $e) {
            if (getenv('MODE') === 'development') {
                error_log("travel_location_images migration: " . $e->getMessage());
            }
        }
    }

    public function getImagesByLocation($locationId) {
        $this->migrateSourceColumn();
        $query = "SELECT * FROM travel_location_images WHERE location_id = :location_id ORDER BY sort_order ASC, id ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':location_id', $locationId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addImage($locationId, $filename, $title = null) {
        requireAdminSession(false);

        $title = $title !== null ? trim($title) : null;
        $title = ($title === '' || $title === null) ? 'Untitled' : $title;

        $this->migrateSourceColumn();
        $query = "INSERT INTO travel_location_images (location_id, filename, title, source, sort_order) VALUES (:location_id, :filename, :title, 'upload', :sort_order)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':location_id', $locationId, PDO::PARAM_INT);
        $stmt->bindValue(':filename', $filename, PDO::PARAM_STR);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':sort_order', 0, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function addGalleryImageRef($locationId, $galleryImageId, $galleryFilename) {
        requireAdminSession(false);

        $this->migrateSourceColumn();
        $query = "INSERT INTO travel_location_images (location_id, filename, title, source, gallery_image_id, sort_order) 
                  VALUES (:location_id, :filename, :title, 'gallery', :gallery_image_id, :sort_order)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':location_id', $locationId, PDO::PARAM_INT);
        $stmt->bindValue(':filename', $galleryFilename, PDO::PARAM_STR);
        $stmt->bindValue(':title', 'Gallery image', PDO::PARAM_STR);
        $stmt->bindValue(':gallery_image_id', $galleryImageId, PDO::PARAM_INT);
        $stmt->bindValue(':sort_order', 0, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteImage($imageId) {
        requireAdminSession(false);

        $query = "SELECT filename, source FROM travel_location_images WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $imageId, PDO::PARAM_INT);
        $stmt->execute();
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            return false;
        }

        $query = "DELETE FROM travel_location_images WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $imageId, PDO::PARAM_INT);
        $result = $stmt->execute();

        // Only delete the physical file if it was an uploaded image (not a gallery reference)
        if ($result && !empty($image['filename']) && ($image['source'] ?? 'upload') === 'upload') {
            $filepath = dirname(__DIR__) . '/uploads/travel/' . $image['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

        return $result;
    }

    public function getTotalImagesByLocation($locationId) {
        $query = "SELECT COUNT(*) FROM travel_location_images WHERE location_id = :location_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':location_id', $locationId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}
