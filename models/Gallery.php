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
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        // Create gallery_albums table
        if ($driver === 'sqlite') {
            $albumQuery = "CREATE TABLE IF NOT EXISTS gallery_albums (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                sort_order INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $albumQuery = "CREATE TABLE IF NOT EXISTS gallery_albums (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
        }
        $this->db->exec($albumQuery);

        // Create gallery_images table
        if ($driver === 'sqlite') {
            $query = "CREATE TABLE IF NOT EXISTS gallery_images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename VARCHAR(255) NOT NULL,
                title VARCHAR(255),
                album_id INTEGER,
                uploaded_by INTEGER,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_archived INTEGER NOT NULL DEFAULT 0,
                archived_at TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (uploaded_by) REFERENCES users(id),
                FOREIGN KEY (album_id) REFERENCES gallery_albums(id)
            )";
        } else {
            $query = "CREATE TABLE IF NOT EXISTS gallery_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                title VARCHAR(255),
                album_id INT NULL,
                uploaded_by INT,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_archived TINYINT(1) NOT NULL DEFAULT 0,
                archived_at TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (uploaded_by) REFERENCES users(id),
                FOREIGN KEY (album_id) REFERENCES gallery_albums(id)
            )";
        }
        
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
        try {
            $this->db->exec("ALTER TABLE gallery_images ADD COLUMN album_id INT NULL");
        } catch (PDOException $e) {
            // Column already exists - ignore
        }
    }

    // ==================== ALBUM METHODS ====================

    public function getAllAlbums() {
        $query = "SELECT * FROM gallery_albums ORDER BY sort_order ASC, name ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAlbumById($id) {
        $query = "SELECT * FROM gallery_albums WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addAlbum($name, $description = null) {
        requireAdminSession(false);
        
        $query = "INSERT INTO gallery_albums (name, description, sort_order) VALUES (:name, :description, :sort_order)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->bindValue(':sort_order', 0, PDO::PARAM_INT);
        $stmt->execute();
        return $this->db->lastInsertId();
    }

    public function updateAlbum($id, $data) {
        requireAdminSession(false);
        
        $fields = [];
        $params = [':id' => $id];
        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params[':name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $fields[] = 'description = :description';
            $params[':description'] = $data['description'];
        }
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = :sort_order';
            $params[':sort_order'] = $data['sort_order'];
        }
        if (empty($fields)) return false;
        
        $query = "UPDATE gallery_albums SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function deleteAlbum($id) {
        requireAdminSession(false);
        
        // First, set album_id to NULL for all images in this album
        $query = "UPDATE gallery_images SET album_id = NULL WHERE album_id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Then delete the album
        $query = "DELETE FROM gallery_albums WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    public function getImagesByAlbum($albumId) {
        $query = "SELECT * FROM gallery_images WHERE album_id = :album_id AND is_archived = 0 ORDER BY uploaded_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':album_id', $albumId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalImagesInAlbum($albumId) {
        $query = "SELECT COUNT(*) FROM gallery_images WHERE album_id = :album_id AND is_archived = 0";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':album_id', $albumId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getImagesWithoutAlbum() {
        $query = "SELECT * FROM gallery_images WHERE album_id IS NULL AND is_archived = 0 ORDER BY uploaded_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAlbumsWithImages() {
        $albums = $this->getAllAlbums();
        $result = [];
        
        foreach ($albums as &$album) {
            $images = $this->getImagesByAlbum($album['id']);
            $album['image_count'] = count($images);
            $album['first_image'] = !empty($images) ? $images[0] : null;
            $album['images'] = $images;
            $result[] = $album;
        }
        
        // Also get unassigned images
        $unassigned = $this->getImagesWithoutAlbum();
        $result[] = [
            'id' => null,
            'name' => 'Unsorted',
            'description' => 'Images without an album',
            'image_count' => count($unassigned),
            'first_image' => !empty($unassigned) ? $unassigned[0] : null,
            'images' => $unassigned,
            'is_unassigned' => true
        ];
        
        return $result;
    }

    public function assignImageToAlbum($imageId, $albumId) {
        requireAdminSession(false);
        
        // albumId can be null (to unassign)
        $query = "UPDATE gallery_images SET album_id = :album_id WHERE id = :id";
        $stmt = $this->db->prepare($query);
        if ($albumId === null || $albumId === 0) {
            $stmt->bindValue(':album_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':album_id', $albumId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':id', $imageId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // ==================== IMAGE METHODS ====================

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
            $albumId = $imageData['album_id'] ?? null;
        } else {
            $filename = $imageData;
            $title = null;
            $albumId = null;
        }
        
        // Process the title - trim whitespace and ensure it's not empty
        $title = $title !== null ? trim($title) : null;
        $title = ($title === '' || $title === null) ? 'Untitled' : $title;  // Set default title if empty
        
        $query = "INSERT INTO gallery_images (filename, title, album_id, uploaded_by) VALUES (:filename, :title, :album_id, :uploaded_by)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':filename', $filename, PDO::PARAM_STR);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        if ($albumId === null || $albumId === 0) {
            $stmt->bindValue(':album_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':album_id', $albumId, PDO::PARAM_INT);
        }
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
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowFunc = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";
        $query = "UPDATE gallery_images SET is_archived = 1, archived_at = $nowFunc WHERE id = :id";
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
        if (isset($data['album_id'])) {
            $fields[] = 'album_id = :album_id';
            $params[':album_id'] = $data['album_id'];
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
