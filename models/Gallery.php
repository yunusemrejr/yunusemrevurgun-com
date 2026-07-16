<?php
require_once __DIR__ . '/../config/setPath.php';

 
require_once __DIR__ . '/Database.php';

class Gallery {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() {
        $query = "CREATE TABLE IF NOT EXISTS gallery_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            title VARCHAR(255),
            uploaded_by INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            archived_at TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )";
        
        $this->db->exec($query);

        // Add is_archived column if missing (existing tables)
        try {
            $this->db->exec("ALTER TABLE gallery_images ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
        } catch (PDOException $e) {
            // Column already exists - ignore
        }
        try {
            $this->db->exec("ALTER TABLE gallery_images ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // Column already exists - ignore
        }
    }

    public function getAllImages($includeArchived = false) {
        if ($includeArchived) {
            $query = "SELECT * FROM gallery_images ORDER BY uploaded_at DESC";
        } else {
            $query = "SELECT * FROM gallery_images WHERE is_archived = 0 ORDER BY uploaded_at DESC";
        }
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addImage($imageData) {
        requireAdminSession(false);
        
        // Handle both array and single parameter formats for backward compatibility
        if (is_array($imageData)) {
            $filename = $imageData['filename'];
            $title = $imageData['title'] ?? null;
        } else {
            $filename = $imageData;
            $title = null;
        }
        
        // Process the title - trim whitespace and ensure it's not empty
        $title = $title !== null ? trim($title) : null;
        $title = ($title === '' || $title === null) ? 'Untitled' : $title;  // Set default title if empty
        
        $query = "INSERT INTO gallery_images (filename, title, uploaded_by) VALUES (:filename, :title, :uploaded_by)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':filename', $filename, PDO::PARAM_STR);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':uploaded_by', $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getImageById($id) {
        $query = "SELECT * FROM gallery_images WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function archiveImage($id) {
        requireAdminSession(false);
        $query = "UPDATE gallery_images SET is_archived = 1, archived_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function unarchiveImage($id) {
        requireAdminSession(false);
        $query = "UPDATE gallery_images SET is_archived = 0, archived_at = NULL WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function updateImage($id, $data) {
        requireAdminSession(false);
        $fields = [];
        $params = [':id' => $id];
        if (isset($data['title'])) {
            $fields[] = 'title = :title';
            $params[':title'] = $data['title'];
        }
        if (empty($fields)) return false;
        $query = "UPDATE gallery_images SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function deleteImage($id) {
        requireAdminSession(false);
        // Get the filename first
        $query = "SELECT filename FROM gallery_images WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            return false;
        }

        // Delete from database first (safer: if DB delete fails, file is preserved)
        $query = "DELETE FROM gallery_images WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $result = $stmt->execute();

        // Delete the file only after DB deletion succeeds
        if ($result && $image['filename']) {
            $filepath = dirname(__DIR__) . '/uploads/gallery/' . $image['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

        return $result;
    }
 

    public function getImages($offset = 0, $limit = 12) {  // 12 images per page for gallery
        $query = "SELECT * FROM gallery_images 
                 ORDER BY uploaded_at DESC 
                 LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalImages($archived = false) {
        if ($archived) {
            $query = "SELECT COUNT(*) FROM gallery_images WHERE is_archived = 1";
        } else {
            $query = "SELECT COUNT(*) FROM gallery_images WHERE is_archived = 0";
        }
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getPaginatedImages($offset = 0, $limit = 12, $archived = false) {
        if ($archived) {
            $query = "SELECT * FROM gallery_images WHERE is_archived = 1 ORDER BY archived_at DESC LIMIT :limit OFFSET :offset";
        } else {
            $query = "SELECT * FROM gallery_images WHERE is_archived = 0 ORDER BY id DESC LIMIT :limit OFFSET :offset";
        }
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentImages($limit = 5) {
        try {
            $query = "SELECT id, filename, title, uploaded_at FROM gallery_images WHERE is_archived = 0 ORDER BY uploaded_at DESC LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            if (getenv('MODE') === 'development') {
                error_log("Error in getRecentImages: " . $e->getMessage());
            }
            return [];
        }
    }

    // Aliases for admin gallery view compatibility
    public function getActiveImages($offset = 0, $limit = 12) {
        return $this->getPaginatedImages($offset, $limit, false);
    }

    public function getArchivedImages($offset = 0, $limit = 12) {
        return $this->getPaginatedImages($offset, $limit, true);
    }

    public function getTotalActiveImages() {
        return $this->getTotalImages(false);
    }

    public function getTotalArchivedImages() {
        return $this->getTotalImages(true);
    }
}