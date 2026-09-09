<?php
require_once __DIR__ . '/../config/setPath.php';
require_once __DIR__ . '/Database.php';

class Videos {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $query = "CREATE TABLE IF NOT EXISTS videos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL DEFAULT 'Untitled',
                description TEXT,
                type TEXT NOT NULL DEFAULT 'upload' CHECK(type IN ('upload', 'url')),
                filename TEXT,
                original_filename TEXT,
                url TEXT,
                platform TEXT,
                video_id TEXT,
                thumbnail_url TEXT,
                duration INT DEFAULT 0,
                file_size INT DEFAULT 0,
                uploaded_by INT,
                uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT NULL,
                is_archived INTEGER NOT NULL DEFAULT 0,
                archived_at TEXT DEFAULT NULL,
                FOREIGN KEY (uploaded_by) REFERENCES users(id)
            )";
        } else {
            $query = "CREATE TABLE IF NOT EXISTS videos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL DEFAULT 'Untitled',
                description TEXT,
                type ENUM('upload', 'url') NOT NULL DEFAULT 'upload',
                filename VARCHAR(255),
                original_filename VARCHAR(255),
                url TEXT,
                platform VARCHAR(50),
                video_id VARCHAR(255),
                thumbnail_url VARCHAR(500),
                duration INT DEFAULT 0,
                file_size INT DEFAULT 0,
                uploaded_by INT,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                is_archived TINYINT(1) NOT NULL DEFAULT 0,
                archived_at TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (uploaded_by) REFERENCES users(id)
            )";
        }
        $this->db->exec($query);

        // Add columns if missing (existing tables - migrations)
        $columns = ['description', 'original_filename', 'duration', 'file_size', 'url', 'platform', 'video_id', 'thumbnail_url'];
        foreach ($columns as $col) {
            try {
                $this->db->exec("ALTER TABLE videos ADD COLUMN $col VARCHAR(255) DEFAULT NULL");
            } catch (PDOException $e) {
                // Column already exists - ignore
            }
        }
        if ($driver !== 'sqlite') {
            try {
                $this->db->exec("ALTER TABLE videos ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
            } catch (PDOException $e) {}
            try {
                $this->db->exec("ALTER TABLE videos ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
            } catch (PDOException $e) {}
            try {
                $this->db->exec("ALTER TABLE videos ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL");
            } catch (PDOException $e) {}
        } else {
            try {
                $this->db->exec("ALTER TABLE videos ADD COLUMN is_archived INTEGER NOT NULL DEFAULT 0");
            } catch (PDOException $e) {}
            try {
                $this->db->exec("ALTER TABLE videos ADD COLUMN archived_at TEXT DEFAULT NULL");
            } catch (PDOException $e) {}
        }
    }

    public function getAllVideos($includeArchived = false) {
        if ($includeArchived) {
            $query = "SELECT * FROM videos ORDER BY uploaded_at DESC";
        } else {
            $query = "SELECT * FROM videos WHERE is_archived = 0 ORDER BY uploaded_at DESC";
        }
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVideoById($id) {
        $query = "SELECT * FROM videos WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addVideo($data) {
        requireAdminSession(false);
        $query = "INSERT INTO videos (title, description, type, filename, original_filename, url, platform, video_id, thumbnail_url, duration, file_size, uploaded_by) 
                  VALUES (:title, :description, :type, :filename, :original_filename, :url, :platform, :video_id, :thumbnail_url, :duration, :file_size, :uploaded_by)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':title', $data['title'] ?? 'Untitled', PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':type', $data['type'] ?? 'upload', PDO::PARAM_STR);
        $stmt->bindValue(':filename', $data['filename'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':original_filename', $data['original_filename'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':url', $data['url'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':platform', $data['platform'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':video_id', $data['video_id'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':thumbnail_url', $data['thumbnail_url'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':duration', $data['duration'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':file_size', $data['file_size'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':uploaded_by', $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function updateVideo($id, $data) {
        requireAdminSession(false);
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = ['title', 'description', 'type', 'filename', 'original_filename', 'url', 'platform', 'video_id', 'thumbnail_url', 'duration', 'file_size'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $query = "UPDATE videos SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function archiveVideo($id) {
        requireAdminSession(false);
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowFunc = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";
        $query = "UPDATE videos SET is_archived = 1, archived_at = $nowFunc WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function unarchiveVideo($id) {
        requireAdminSession(false);
        $query = "UPDATE videos SET is_archived = 0, archived_at = NULL WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteVideo($id) {
        requireAdminSession(false);
        $query = "SELECT filename, type FROM videos WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $video = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$video) return false;

        $query = "DELETE FROM videos WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $result = $stmt->execute();

        if ($result && $video['type'] === 'upload' && $video['filename']) {
            require_once __DIR__ . '/../includes/upload_files.php';
            removeUploadFile(dirname(__DIR__) . '/uploads/videos', $video['filename']);
        }

        return $result;
    }

    public function getTotalVideos($archived = false) {
        if ($archived) {
            $query = "SELECT COUNT(*) FROM videos WHERE is_archived = 1";
        } else {
            $query = "SELECT COUNT(*) FROM videos WHERE is_archived = 0";
        }
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getRecentVideos($limit = 5) {
        try {
            $query = "SELECT id, title, type, filename, url, platform, thumbnail_url, uploaded_at FROM videos WHERE is_archived = 0 ORDER BY uploaded_at DESC LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            if (getenv('MODE') === 'development') {
                error_log("Error in getRecentVideos: " . $e->getMessage());
            }
            return [];
        }
    }

    public function getPaginatedVideos($offset = 0, $limit = 20, $archived = false) {
        if ($archived) {
            $query = "SELECT * FROM videos WHERE is_archived = 1 ORDER BY archived_at DESC LIMIT :limit OFFSET :offset";
        } else {
            $query = "SELECT * FROM videos WHERE is_archived = 0 ORDER BY uploaded_at DESC LIMIT :limit OFFSET :offset";
        }
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveVideos($offset = 0, $limit = 20) {
        return $this->getPaginatedVideos($offset, $limit, false);
    }

    public function getArchivedVideos($offset = 0, $limit = 20) {
        return $this->getPaginatedVideos($offset, $limit, true);
    }

    public function getTotalActiveVideos() {
        return $this->getTotalVideos(false);
    }

    public function getTotalArchivedVideos() {
        return $this->getTotalVideos(true);
    }
}
