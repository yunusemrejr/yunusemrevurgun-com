<?php
/**
 * Rmrp — "Random Memories for Random People" (/rmrp).
 *
 * A sibling of the Updates log: same driver-branched DDL + CRUD shape and the
 * same markdown content pipeline (models/RichText.php), but a separate table
 * and separate routes/feed. Deliberate differences from Updates:
 *   - no `created_by` column (memories have no author/pfp area)
 *   - `memory_date` instead of `update_date`
 *   - independent sitemap regeneration (Sitemap.php lists both feeds)
 */
require_once __DIR__ . '/../config/setPath.php';
require_once __DIR__ . '/Database.php';

class Rmrp {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $query = "CREATE TABLE IF NOT EXISTS rmrp_memories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NOT NULL,
                memory_date TEXT NOT NULL,
                category TEXT,
                importance TEXT DEFAULT 'medium',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $query = "CREATE TABLE IF NOT EXISTS rmrp_memories (
                id INT PRIMARY KEY AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                memory_date DATE NOT NULL,
                category VARCHAR(50),
                importance ENUM('low', 'medium', 'high') DEFAULT 'medium',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
        }

        $this->db->exec($query);

        // Same migration guard as Updates (older tables may lack updated_at).
        $this->addUpdatedAtColumnIfNotExist();
    }

    private function addUpdatedAtColumnIfNotExist() {
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $columns = [];
                $result = $this->db->query("PRAGMA table_info(rmrp_memories)");
                foreach ($result as $row) {
                    $columns[] = $row['name'];
                }

                if (!in_array('updated_at', $columns)) {
                    $this->db->exec("ALTER TABLE rmrp_memories ADD COLUMN updated_at TEXT DEFAULT CURRENT_TIMESTAMP");
                }
            } else {
                $result = $this->db->query("SHOW COLUMNS FROM rmrp_memories LIKE 'updated_at'");
                if ($result->rowCount() == 0) {
                    $this->db->exec("ALTER TABLE rmrp_memories ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
                }
            }
        } catch (Exception $e) {
            error_log("Error adding updated_at column to rmrp_memories table: " . $e->getMessage());
        }
    }

    public function getAllMemories() {
        $query = "SELECT * FROM rmrp_memories ORDER BY memory_date DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMemoryById($id) {
        $query = "SELECT * FROM rmrp_memories WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createMemory($data) {
        requireAdminSession(true);
        $query = "INSERT INTO rmrp_memories (
                    title, description, memory_date, category, importance
                ) VALUES (
                    :title, :description, :memory_date, :category, :importance
                )";

        $stmt = $this->db->prepare($query);

        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['content'], PDO::PARAM_STR);
        $stmt->bindValue(':memory_date', $data['date'], PDO::PARAM_STR);
        $stmt->bindValue(':category', $data['category'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':importance', $data['importance'] ?? 'medium', PDO::PARAM_STR);

        $result = $stmt->execute();

        if ($result) {
            $this->regenerateSitemap();
        }

        return $result;
    }

    public function updateMemory($id, $data) {
        requireAdminSession(true);

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowFunc = ($driver === 'sqlite') ? "datetime('now')" : "CURRENT_TIMESTAMP";

        $query = "UPDATE rmrp_memories SET
                  title = :title,
                  description = :description,
                  memory_date = :memory_date,
                  category = :category,
                  importance = :importance,
                  updated_at = $nowFunc
                  WHERE id = :id";

        $stmt = $this->db->prepare($query);

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['content'], PDO::PARAM_STR);
        $stmt->bindValue(':memory_date', $data['date'], PDO::PARAM_STR);
        $stmt->bindValue(':category', $data['category'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':importance', $data['importance'] ?? 'medium', PDO::PARAM_STR);

        $result = $stmt->execute();

        if ($result) {
            $this->regenerateSitemap();
        }

        return $result;
    }

    public function deleteMemory($id) {
        requireAdminSession(true);
        $query = "DELETE FROM rmrp_memories WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        $result = $stmt->execute();

        if ($result && $stmt->rowCount() > 0) {
            $this->regenerateSitemap();
            return true;
        }

        return false;
    }

    private function regenerateSitemap() {
        try {
            require_once __DIR__ . '/Sitemap.php';
            $sitemap = new Sitemap();
            $result = $sitemap->forceRegenerate();

            if ($result) {
                error_log("Sitemap successfully regenerated after memory modification");
            } else {
                error_log("Sitemap regeneration failed after memory modification");
            }
        } catch (Exception $e) {
            error_log("Failed to regenerate sitemap after memory modification: " . $e->getMessage());
        }
    }

    public function getTotalMemories() {
        $query = "SELECT COUNT(*) FROM rmrp_memories";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // Dashboard compatibility (recent memories with excerpt-friendly fields).
    public function getMemories($limit = 10) {
        try {
            $query = "SELECT id, title, memory_date, description FROM rmrp_memories ORDER BY memory_date DESC LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getMemories: " . $e->getMessage());
            return [];
        }
    }

    public function getPaginatedMemories($offset, $limit) {
        $query = "SELECT * FROM rmrp_memories ORDER BY memory_date DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
