<?php
require_once __DIR__ . '/../config/setPath.php';
 
require_once __DIR__ . '/Database.php';

class Updates {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
             $query = "CREATE TABLE IF NOT EXISTS updates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NOT NULL,
                update_date TEXT NOT NULL,
                category TEXT,
                importance TEXT DEFAULT 'medium',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                created_by INTEGER NOT NULL
            )";
        } else {
            $query = "CREATE TABLE IF NOT EXISTS updates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                update_date DATE NOT NULL,
                category VARCHAR(50),
                importance ENUM('low', 'medium', 'high') DEFAULT 'medium',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL
            )";
        }
        
        $this->db->exec($query);
        
        // Add updated_at column to existing table if it doesn't exist
        $this->addUpdatedAtColumnIfNotExist();
    }

    /**
     * Add updated_at column to existing updates table if it doesn't exist
     */
    private function addUpdatedAtColumnIfNotExist() {
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            
            if ($driver === 'sqlite') {
                $columns = [];
                $result = $this->db->query("PRAGMA table_info(updates)");
                foreach ($result as $row) {
                    $columns[] = $row['name'];
                }
                
                if (!in_array('updated_at', $columns)) {
                    $this->db->exec("ALTER TABLE updates ADD COLUMN updated_at TEXT DEFAULT CURRENT_TIMESTAMP");
                }
            } else {
                // Check if updated_at column exists
                $result = $this->db->query("SHOW COLUMNS FROM updates LIKE 'updated_at'");
                if ($result->rowCount() == 0) {
                    $this->db->exec("ALTER TABLE updates ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
                }
            }
        } catch (Exception $e) {
            error_log("Error adding updated_at column to updates table: " . $e->getMessage());
        }
    }

    public function getAllUpdates() {
        $query = "SELECT * FROM updates ORDER BY update_date DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUpdateById($id) {
        $query = "SELECT * FROM updates WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createUpdate($data) {
        requireAdminSession(true);
        $query = "INSERT INTO updates (
                    title, description, update_date, category, importance, created_by
                ) VALUES (
                    :title, :description, :update_date, :category, :importance, :created_by
                )";
        
        $stmt = $this->db->prepare($query);
        
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['content'], PDO::PARAM_STR);
        $stmt->bindValue(':update_date', $data['date'], PDO::PARAM_STR);
        $stmt->bindValue(':category', $data['category'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':importance', $data['importance'] ?? 'medium', PDO::PARAM_STR);
        $stmt->bindValue(':created_by', $_SESSION['user_id'] ?? 1, PDO::PARAM_INT);
        
        $result = $stmt->execute();
        
        // Regenerate sitemap after creating update
        if ($result) {
            $this->regenerateSitemap();
        }
        
        return $result;
    }

    public function updateUpdate($id, $data) {
        requireAdminSession(true);
        
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowFunc = ($driver === 'sqlite') ? "datetime('now')" : "CURRENT_TIMESTAMP";
        
        $query = "UPDATE updates SET 
                  title = :title, 
                  description = :description, 
                  update_date = :update_date,
                  category = :category,
                  importance = :importance,
                  updated_at = $nowFunc
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['content'], PDO::PARAM_STR);
        $stmt->bindValue(':update_date', $data['date'], PDO::PARAM_STR);
        $stmt->bindValue(':category', $data['category'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':importance', $data['importance'] ?? 'medium', PDO::PARAM_STR);
        
        $result = $stmt->execute();
        
        // Regenerate sitemap after updating update
        if ($result) {
            $this->regenerateSitemap();
        }
        
        return $result;
    }

    public function deleteUpdate($id) {
        requireAdminSession(true);
        $query = "DELETE FROM updates WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        $result = $stmt->execute();
        
        // Only regenerate sitemap and return true if a row was actually deleted
        if ($result && $stmt->rowCount() > 0) {
            $this->regenerateSitemap();
            return true;
        }
        
        return false;
    }

    public function updateStatus($id, $status) {
        // This method is not needed since the updates table doesn't have a status column
        // But we'll keep it for compatibility with the existing code
        return true;
    }

    /**
     * Regenerate sitemap after updates are modified
     */
    private function regenerateSitemap() {
        try {
            require_once __DIR__ . '/Sitemap.php';
            $sitemap = new Sitemap();
            $result = $sitemap->forceRegenerate();
            
            if ($result) {
                error_log("Sitemap successfully regenerated after update modification");
            } else {
                error_log("Sitemap regeneration failed after update modification");
            }
        } catch (Exception $e) {
            error_log("Failed to regenerate sitemap after update modification: " . $e->getMessage());
        }
    }

    public function getTotalUpdates() {
        $query = "SELECT COUNT(*) FROM updates";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // Add this method back for dashboard compatibility
    public function getUpdates($limit = 10) {

        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $hasContentColumn = false;
            
            if ($driver === 'sqlite') {
                 $result = $this->db->query("PRAGMA table_info(updates)");
                 foreach ($result as $row) {
                     if ($row['name'] === 'content') {
                         $hasContentColumn = true;
                         break;
                     }
                 }
            } else {
                // First, check if the updates table has a content column
                $query = "SHOW COLUMNS FROM updates LIKE 'content'";
                $stmt = $this->db->prepare($query);
                $stmt->execute();
                $hasContentColumn = $stmt->rowCount() > 0;
            }
            
            // Build the query based on the table structure
            if ($hasContentColumn) {
                $query = "SELECT id, title, update_date, content FROM updates ORDER BY update_date DESC LIMIT :limit";
            } else {
                $query = "SELECT id, title, update_date, '' as content FROM updates ORDER BY update_date DESC LIMIT :limit";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUpdates: " . $e->getMessage());
            return [];
        }
    }

    public function getPaginatedUpdates($offset, $limit) {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
            $query = "SELECT * FROM updates ORDER BY update_date DESC LIMIT :limit OFFSET :offset";
        } else {
            $query = "SELECT * FROM updates ORDER BY update_date DESC LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Legacy methods for backward compatibility
    public function getUpdate($id) {
        return $this->getUpdateById($id);
    }
}

