<?php
require_once __DIR__ . '/../config/setPath.php';
require_once __DIR__ . '/Database.php';

class Music {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() {
        $query = "CREATE TABLE IF NOT EXISTS music_tracks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255),
            title VARCHAR(255) NOT NULL DEFAULT 'Untitled',
            description TEXT,
            recorded_at DATE DEFAULT NULL,
            duration FLOAT DEFAULT 0,
            file_size INT DEFAULT 0,
            uploaded_by INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            sort_order INT DEFAULT 0,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            archived_at TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )";
        $this->db->exec($query);

        // Add columns if missing (existing tables)
        $columns = ['original_filename', 'description', 'recorded_at', 'duration', 'file_size', 'sort_order'];
        foreach ($columns as $col) {
            try {
                $this->db->exec("ALTER TABLE music_tracks ADD COLUMN $col VARCHAR(255) DEFAULT NULL");
            } catch (PDOException $e) {
                // Column already exists - ignore
            }
        }
        try {
            $this->db->exec("ALTER TABLE music_tracks ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
        } catch (PDOException $e) {}
        try {
            $this->db->exec("ALTER TABLE music_tracks ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
        } catch (PDOException $e) {}
        try {
            $this->db->exec("ALTER TABLE music_tracks ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL");
        } catch (PDOException $e) {}
    }

    public function getAllTracks($includeArchived = false) {
        if ($includeArchived) {
            $query = "SELECT * FROM music_tracks ORDER BY sort_order ASC, uploaded_at DESC";
        } else {
            $query = "SELECT * FROM music_tracks WHERE is_archived = 0 ORDER BY sort_order ASC, uploaded_at DESC";
        }
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTrackById($id) {
        $query = "SELECT * FROM music_tracks WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addTrack($data) {
        requireAdminSession(false);
        $query = "INSERT INTO music_tracks (filename, original_filename, title, description, recorded_at, duration, file_size, uploaded_by, sort_order) 
                  VALUES (:filename, :original_filename, :title, :description, :recorded_at, :duration, :file_size, :uploaded_by, :sort_order)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':filename', $data['filename'], PDO::PARAM_STR);
        $stmt->bindValue(':original_filename', $data['original_filename'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':title', $data['title'] ?? 'Untitled', PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':recorded_at', $data['recorded_at'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':duration', $data['duration'] ?? 0, PDO::PARAM_STR);
        $stmt->bindValue(':file_size', $data['file_size'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':uploaded_by', $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':sort_order', $data['sort_order'] ?? 0, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function updateTrack($id, $data) {
        requireAdminSession(false);
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = ['title', 'description', 'recorded_at', 'sort_order'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $query = "UPDATE music_tracks SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function archiveTrack($id) {
        requireAdminSession(false);
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowFunc = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";
        $query = "UPDATE music_tracks SET is_archived = 1, archived_at = $nowFunc WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function unarchiveTrack($id) {
        requireAdminSession(false);
        $query = "UPDATE music_tracks SET is_archived = 0, archived_at = NULL WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteTrack($id) {
        requireAdminSession(false);
        $query = "SELECT filename FROM music_tracks WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $track = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$track) return false;

        $query = "DELETE FROM music_tracks WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $result = $stmt->execute();

        if ($result && $track['filename']) {
            $filepath = dirname(__DIR__) . '/uploads/music/' . $track['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

        return $result;
    }

    public function getTotalTracks($archived = false) {
        if ($archived) {
            $query = "SELECT COUNT(*) FROM music_tracks WHERE is_archived = 1";
        } else {
            $query = "SELECT COUNT(*) FROM music_tracks WHERE is_archived = 0";
        }
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getRecentTracks($limit = 5) {
        try {
            $query = "SELECT id, filename, title, uploaded_at FROM music_tracks WHERE is_archived = 0 ORDER BY uploaded_at DESC LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            if (getenv('MODE') === 'development') {
                error_log("Error in getRecentTracks: " . $e->getMessage());
            }
            return [];
        }
    }

    public function getPaginatedTracks($offset = 0, $limit = 20, $archived = false) {
        if ($archived) {
            $query = "SELECT * FROM music_tracks WHERE is_archived = 1 ORDER BY archived_at DESC LIMIT :limit OFFSET :offset";
        } else {
            $query = "SELECT * FROM music_tracks WHERE is_archived = 0 ORDER BY sort_order ASC, uploaded_at DESC LIMIT :limit OFFSET :offset";
        }
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveTracks($offset = 0, $limit = 20) {
        return $this->getPaginatedTracks($offset, $limit, false);
    }

    public function getArchivedTracks($offset = 0, $limit = 20) {
        return $this->getPaginatedTracks($offset, $limit, true);
    }

    public function getTotalActiveTracks() {
        return $this->getTotalTracks(false);
    }

    public function getTotalArchivedTracks() {
        return $this->getTotalTracks(true);
    }
}
