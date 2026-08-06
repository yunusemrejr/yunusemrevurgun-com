<?php
require_once __DIR__ . '/../config/setPath.php';
 
require_once __DIR__ . '/Database.php';
// createUpdate() assigns a stable Mastodon idempotency key for cross-posting.
require_once __DIR__ . '/../services/MastodonService.php';

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
                created_by INTEGER NOT NULL,
                mastodon_status_id TEXT,
                mastodon_status_url TEXT,
                mastodon_sync_status TEXT DEFAULT 'not_requested',
                mastodon_last_error TEXT,
                mastodon_attempt_count INTEGER DEFAULT 0,
                mastodon_published_at TEXT,
                mastodon_idempotency_key TEXT
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
                created_by INT NOT NULL,
                mastodon_status_id VARCHAR(255),
                mastodon_status_url VARCHAR(500),
                mastodon_sync_status ENUM('not_requested', 'pending', 'published', 'failed') DEFAULT 'not_requested',
                mastodon_last_error TEXT,
                mastodon_attempt_count INT DEFAULT 0,
                mastodon_published_at DATETIME NULL,
                mastodon_idempotency_key VARCHAR(64)
            )";
        }
        
        $this->db->exec($query);
        
        // Add updated_at column to existing table if it doesn't exist
        $this->addUpdatedAtColumnIfNotExist();
        // Add Mastodon sync columns to existing tables (prod MySQL + dev SQLite).
        $this->addMastodonColumnsIfNotExist();
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

    /**
     * Add Mastodon sync columns to an existing updates table.
     * NOTE: mastodon_idempotency_key is deliberately NOT a DB-level UNIQUE
     * constraint (SQLite cannot ADD COLUMN with UNIQUE via ALTER TABLE); it is
     * unique by construction — a sha256 of the update id.
     */
    private function addMastodonColumnsIfNotExist() {
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $want = [
                'mastodon_status_id' => "ALTER TABLE updates ADD COLUMN mastodon_status_id %s",
                'mastodon_status_url' => "ALTER TABLE updates ADD COLUMN mastodon_status_url %s",
                'mastodon_sync_status' => "ALTER TABLE updates ADD COLUMN mastodon_sync_status %s DEFAULT 'not_requested'",
                'mastodon_last_error' => "ALTER TABLE updates ADD COLUMN mastodon_last_error %s",
                'mastodon_attempt_count' => "ALTER TABLE updates ADD COLUMN mastodon_attempt_count %s DEFAULT 0",
                'mastodon_published_at' => "ALTER TABLE updates ADD COLUMN mastodon_published_at %s",
                'mastodon_idempotency_key' => "ALTER TABLE updates ADD COLUMN mastodon_idempotency_key %s",
            ];
            $types = ($driver === 'sqlite')
                ? ['TEXT', 'TEXT', 'TEXT', 'TEXT', 'INTEGER', 'TEXT', 'TEXT']
                : ['VARCHAR(255)', 'VARCHAR(500)', "ENUM('not_requested','pending','published','failed')", 'TEXT', 'INT', 'DATETIME NULL', 'VARCHAR(64)'];

            if ($driver === 'sqlite') {
                $cols = [];
                $result = $this->db->query('PRAGMA table_info(updates)');
                foreach ($result as $row) { $cols[] = $row['name']; }
                $i = 0;
                foreach ($want as $col => $tpl) {
                    if (!in_array($col, $cols, true)) {
                        $this->db->exec(sprintf($tpl, $types[$i]));
                    }
                    $i++;
                }
            } else {
                $result = $this->db->query('SHOW COLUMNS FROM updates');
                $cols = [];
                foreach ($result as $row) { $cols[] = $row['Field']; }
                $i = 0;
                foreach ($want as $col => $tpl) {
                    if (!in_array($col, $cols, true)) {
                        $this->db->exec(sprintf($tpl, $types[$i]));
                    }
                    $i++;
                }
            }
        } catch (Exception $e) {
            error_log('Error adding Mastodon columns to updates table: ' . $e->getMessage());
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

    /**
     * Create an update. Returns the new update id on success (int), false on failure.
     * Accepts optional Mastodon sync fields:
     *   post_to_mastodon (bool) — when true the row starts as 'pending' and gets
     *   a stable idempotency key (used by MastodonService so retries never
     *   duplicate the status). When false/absent the row stays 'not_requested'.
     */
    public function createUpdate($data) {
        requireAdminSession(true);
        $postToMastodon = !empty($data['post_to_mastodon']);
        $syncStatus = $postToMastodon ? 'pending' : 'not_requested';

        $query = "INSERT INTO updates (
                    title, description, update_date, category, importance, created_by,
                    mastodon_sync_status, mastodon_attempt_count
                ) VALUES (
                    :title, :description, :update_date, :category, :importance, :created_by,
                    :sync_status, 0
                )";

        $stmt = $this->db->prepare($query);

        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['content'], PDO::PARAM_STR);
        $stmt->bindValue(':update_date', $data['date'], PDO::PARAM_STR);
        $stmt->bindValue(':category', $data['category'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':importance', $data['importance'] ?? 'medium', PDO::PARAM_STR);
        $stmt->bindValue(':created_by', $_SESSION['user_id'] ?? 1, PDO::PARAM_INT);
        $stmt->bindValue(':sync_status', $syncStatus, PDO::PARAM_STR);

        $result = $stmt->execute();

        if ($result) {
            $id = (int) $this->db->lastInsertId();
            // Stable idempotency key derived from the update id — set once, reused on retries.
            if ($postToMastodon) {
                $this->setMastodonIdempotencyKey($id, \MastodonService::idempotencyKeyForUpdate($id));
            }
            $this->regenerateSitemap();
            return $id;
        }

        return false;
    }

    /** Set the stable idempotency key for an update (created at insert time, reused on retries). */
    public function setMastodonIdempotencyKey($id, string $key): bool {
        $stmt = $this->db->prepare('UPDATE updates SET mastodon_idempotency_key = :key WHERE id = :id');
        $stmt->bindValue(':key', $key, PDO::PARAM_STR);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Record the outcome of a Mastodon publish attempt.
     * Whitelisted fields only; never accepts arbitrary columns.
     */
    public function updateMastodonSync($id, array $fields): bool {
        requireAdminSession(true);
        $allowed = [
            'mastodon_status_id', 'mastodon_status_url', 'mastodon_sync_status',
            'mastodon_last_error', 'mastodon_published_at',
        ];
        $sets = [];
        $params = [':id' => (int) $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[] = "$col = :$col";
                $params[":$col"] = $fields[$col];
            }
        }
        if (empty($sets)) return false;
        $stmt = $this->db->prepare('UPDATE updates SET ' . implode(', ', $sets) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    /** Increment the publish-attempt counter (used on every attempt incl. retries). */
    public function incrementMastodonAttempt($id): bool {
        $stmt = $this->db->prepare(
            'UPDATE updates SET mastodon_attempt_count = COALESCE(mastodon_attempt_count, 0) + 1 WHERE id = :id'
        );
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Permanent public URL for an update. In dev the site base differs from the
     * production domain, and Mastodon posts must always link to the live URL.
     */
    public static function canonicalUpdateUrl($id): string {
        $base = (getenv('MODE') === 'production') ? FULL_BASE_PATH : 'https://yunusemrevurgun.com/';
        return rtrim($base, '/') . '/updates/' . (int) $id;
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

