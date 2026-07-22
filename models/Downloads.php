<?php
/**
 * Downloads model
 *
 * Desktop/offline apps published on the public /downloads page. Files are NOT
 * hosted here — `download_url` points to an external release (e.g. GitHub).
 * `platforms` and `dependencies` are stored as JSON arrays of strings.
 * `thumbnail` is an uploaded image filename living in uploads/downloads/.
 */
require_once __DIR__ . '/../config/setPath.php';
require_once __DIR__ . '/Database.php';

class Downloads {
    private $db;

    /** Directory (relative to project root) where thumbnails are stored. */
    const UPLOAD_DIR = 'uploads/downloads';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $query = "CREATE TABLE IF NOT EXISTS downloads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL DEFAULT 'Untitled',
                description TEXT,
                download_url TEXT NOT NULL DEFAULT '',
                thumbnail TEXT,
                platforms TEXT,
                dependencies TEXT,
                created_by INT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT NULL,
                FOREIGN KEY (created_by) REFERENCES users(id)
            )";
        } else {
            $query = "CREATE TABLE IF NOT EXISTS downloads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL DEFAULT 'Untitled',
                description TEXT,
                download_url TEXT NOT NULL,
                thumbnail VARCHAR(255),
                platforms TEXT,
                dependencies TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            )";
        }
        $this->db->exec($query);
    }

    public function getAllDownloads() {
        $stmt = $this->db->query("SELECT * FROM downloads ORDER BY created_at DESC, id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDownloadById($id) {
        $stmt = $this->db->prepare("SELECT * FROM downloads WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addDownload($data) {
        requireAdminSession(false);
        $query = "INSERT INTO downloads (title, description, download_url, thumbnail, platforms, dependencies, created_by)
                  VALUES (:title, :description, :download_url, :thumbnail, :platforms, :dependencies, :created_by)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':title', $data['title'] ?? 'Untitled', PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':download_url', $data['download_url'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':thumbnail', $data['thumbnail'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':platforms', $data['platforms'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':dependencies', $data['dependencies'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':created_by', $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function updateDownload($id, $data) {
        requireAdminSession(false);
        $allowed = ['title', 'description', 'download_url', 'thumbnail', 'platforms', 'dependencies'];
        $fields = [];
        $params = [':id' => $id];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) return false;

        $stmt = $this->db->prepare("UPDATE downloads SET " . implode(', ', $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function deleteDownload($id) {
        requireAdminSession(false);
        $download = $this->getDownloadById($id);
        if (!$download) return false;

        $stmt = $this->db->prepare("DELETE FROM downloads WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $result = $stmt->execute();

        // Remove the thumbnail file once the row is gone.
        if ($result && !empty($download['thumbnail'])) {
            $path = dirname(__DIR__) . '/' . self::UPLOAD_DIR . '/' . $download['thumbnail'];
            if (is_file($path)) {
                unlink($path);
            }
        }
        return $result;
    }

    public function getTotalDownloads() {
        return (int) $this->db->query("SELECT COUNT(*) FROM downloads")->fetchColumn();
    }

    public function getRecentDownloads($limit = 5) {
        $stmt = $this->db->prepare("SELECT id, title, download_url, thumbnail, created_at FROM downloads ORDER BY created_at DESC, id DESC LIMIT :limit");
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Decode a stored JSON list into a clean array of strings.
     * Tolerates legacy comma-separated values.
     */
    public static function decodeList($stored): array {
        if ($stored === null || $stored === '') return [];
        $decoded = json_decode((string) $stored, true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = explode(',', (string) $stored);
        }
        $items = array_map(fn($v) => trim((string) $v), $items);
        return array_values(array_filter($items, fn($v) => $v !== ''));
    }

    /**
     * Normalize user input (array or comma-separated string) into a JSON array
     * string ready for storage. Returns null when the list is empty.
     */
    public static function encodeList($input): ?string {
        if (is_string($input)) {
            $input = explode(',', $input);
        }
        if (!is_array($input)) return null;
        $items = array_map(fn($v) => trim(strip_tags((string) $v)), $input);
        $items = array_values(array_filter($items, fn($v) => $v !== ''));
        if (empty($items)) return null;
        return json_encode($items, JSON_UNESCAPED_UNICODE);
    }
}
