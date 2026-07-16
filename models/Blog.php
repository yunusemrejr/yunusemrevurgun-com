<?php
require_once __DIR__ . '/../config/setPath.php';
 
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sitemap.php';

class Blog {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
             $query = "CREATE TABLE IF NOT EXISTS blog_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                content TEXT NOT NULL,
                excerpt TEXT,
                author_id INTEGER NOT NULL,
                status TEXT DEFAULT 'draft',
                featured_image TEXT,
                meta_keywords TEXT,
                meta_description TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
            )";
        } else {
            $query = "CREATE TABLE IF NOT EXISTS blog_posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                content LONGTEXT NOT NULL,
                excerpt TEXT,
                author_id INT NOT NULL,
                status ENUM('draft', 'published') DEFAULT 'draft',
                featured_image VARCHAR(255),
                meta_keywords TEXT,
                meta_description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
            )";
        }
        
        $this->db->exec($query);
        
        // Add SEO columns to existing table if they don't exist
        $this->addSEOColumnsIfNotExist();
    }

    private function addSEOColumnsIfNotExist() {
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            
            if ($driver === 'sqlite') {
                // SQLite equivalent to check columns
                $columns = [];
                $result = $this->db->query("PRAGMA table_info(blog_posts)");
                foreach ($result as $row) {
                    $columns[] = $row['name'];
                }
                
                if (!in_array('meta_keywords', $columns)) {
                    $this->db->exec("ALTER TABLE blog_posts ADD COLUMN meta_keywords TEXT");
                }
                
                if (!in_array('meta_description', $columns)) {
                    $this->db->exec("ALTER TABLE blog_posts ADD COLUMN meta_description TEXT");
                }
            } else {
                // MySQL
                // Check if meta_keywords column exists
                $result = $this->db->query("SHOW COLUMNS FROM blog_posts LIKE 'meta_keywords'");
                if ($result->rowCount() == 0) {
                    $this->db->exec("ALTER TABLE blog_posts ADD COLUMN meta_keywords TEXT AFTER featured_image");
                }
                
                // Check if meta_description column exists
                $result = $this->db->query("SHOW COLUMNS FROM blog_posts LIKE 'meta_description'");
                if ($result->rowCount() == 0) {
                    $this->db->exec("ALTER TABLE blog_posts ADD COLUMN meta_description TEXT AFTER meta_keywords");
                }
            }
        } catch (Exception $e) {
            if (getenv('MODE') === 'development') {
                error_log("Error adding SEO columns: " . $e->getMessage());
            }
        }
    }

    public function getAllPosts() {
        $query = "SELECT p.*, u.username as author_name 
                 FROM blog_posts p 
                 JOIN users u ON p.author_id = u.id 
                 ORDER BY p.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPosts($offset = 0, $limit = 10) {
        // No need to get all posts first, just get the paginated results
        $query = "SELECT p.*, u.username as author_name 
                 FROM blog_posts p 
                 LEFT JOIN users u ON p.author_id = u.id 
                 WHERE p.status = 'published' 
                 ORDER BY p.created_at DESC 
                 LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a slug exists in the database
     * 
     * @param string $slug The slug to check
     * @return bool True if the slug exists, false otherwise
     */
    public function slugExists($slug) {
        $query = "SELECT COUNT(*) FROM blog_posts WHERE slug = :slug";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }

    // Modify getPublishedPost to include more debugging
    public function getPublishedPost($slug) {
        $this->debug("Looking up post with slug: " . $slug);
        
        // First, check if the post exists at all
        $checkQuery = "SELECT id, status FROM blog_posts WHERE slug = :slug";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $checkStmt->execute();
        $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$checkResult) {
            $this->debug("No post found with slug: " . $slug);
            return null;
        }
        
        if ($checkResult['status'] !== 'published') {
            $this->debug("Post found but status is: " . $checkResult['status']);
            return null;
        }
        
        // Get the full post data
        $query = "SELECT p.*, u.username as author_name 
                 FROM blog_posts p 
                 LEFT JOIN users u ON p.author_id = u.id 
                 WHERE p.slug = :slug 
                 AND p.status = 'published'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $this->debug("Found published post: " . $result['title']);
        }
        
        return $result;
    }

    // For admin editing (by ID, any status)
    public function getPost($id) {
        $query = "SELECT p.*, u.username as author_name 
                 FROM blog_posts p 
                 LEFT JOIN users u ON p.author_id = u.id 
                 WHERE p.id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return null;
        }
        return $result;
    }

    private function isExcerptUnique($excerpt, $excludeId = null) {
        $query = "SELECT COUNT(*) FROM blog_posts WHERE excerpt = :excerpt";
        $params = [':excerpt' => $excerpt];
        
        if ($excludeId) {
            $query .= " AND id != :id";
            $params[':id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() === 0;
    }

    private function isSlugUnique($slug, $excludeId = null) {
        $query = "SELECT COUNT(*) FROM blog_posts WHERE slug = :slug";
        $params = [':slug' => $slug];
        
        if ($excludeId) {
            $query .= " AND id != :id";
            $params[':id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() === 0;
    }

    public function createPost($data) {
        requireAdminSession(true);
        try {
            $data = $this->preparePostData($data);

            // Create slug from title if not provided
            $slug = !empty($data['slug']) ? $data['slug'] : $this->createSlug($data['title']);
            
            $query = "INSERT INTO blog_posts (title, slug, content, excerpt, author_id, status, featured_image, meta_keywords, meta_description) 
                     VALUES (:title, :slug, :content, :excerpt, :author_id, :status, :featured_image, :meta_keywords, :meta_description)";
            
            $stmt = $this->db->prepare($query);
            
            $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
            $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
            $stmt->bindValue(':content', $data['content'], PDO::PARAM_STR);
            $stmt->bindValue(':excerpt', $data['excerpt'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':author_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':status', $data['status'], PDO::PARAM_STR);
            $stmt->bindValue(':featured_image', $data['featured_image'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':meta_keywords', $data['meta_keywords'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':meta_description', $data['meta_description'] ?? null, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                // Generate new sitemap
                try {
                    $sitemap = new Sitemap();
                    $sitemap->generateSitemapXML();
                } catch (Exception $sitemapError) {
                    if (getenv('MODE') === 'development') {
                        error_log("Sitemap regeneration failed after blog post creation: " . $sitemapError->getMessage());
                    }
                    // Don't fail the whole operation if sitemap fails
                }
                return true;
            }
            return false;
        } catch (Exception $e) {
            if (getenv('MODE') === 'development') {
                error_log("Error creating blog post: " . $e->getMessage());
            }
            throw $e; // Re-throw to be caught by the controller
        }
    }

    private function uploadFeaturedImage($file) {
        $uploadDir = __DIR__ . '/../uploads/blog/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return 'uploads/blog/' . $filename;
        }
        
        return null;
    }

    public function updatePost($id, $data) {
        requireAdminSession(true);
        try {
            $data = $this->preparePostData($data);

            // First check if post exists
            $existingPost = $this->getPostById($id);
            if (!$existingPost) {
                return false;
            }

            // Check if slug is unique (excluding current post)
            if (!empty($data['slug']) && !$this->isSlugUnique($data['slug'], $id)) {
                $baseSlug = $data['slug'];
                $suffix = 1;
                while (!$this->isSlugUnique($baseSlug . '-' . $suffix, $id)) {
                    $suffix++;
                }
                $data['slug'] = $baseSlug . '-' . $suffix;
            }

            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowFunc = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            // Start building the query
            $query = "UPDATE blog_posts SET 
                     title = :title,
                     content = :content,
                     excerpt = :excerpt,
                     status = :status,
                     slug = :slug,
                     featured_image = :featured_image,
                     meta_keywords = :meta_keywords,
                     meta_description = :meta_description,
                     updated_at = $nowFunc
                     WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            
            // Bind all parameters
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
            $stmt->bindValue(':content', $data['content'], PDO::PARAM_STR);
            $stmt->bindValue(':excerpt', $data['excerpt'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':status', $data['status'], PDO::PARAM_STR);
            $stmt->bindValue(':slug', $data['slug'], PDO::PARAM_STR);
            $stmt->bindValue(':meta_keywords', $data['meta_keywords'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':meta_description', $data['meta_description'] ?? null, PDO::PARAM_STR);
            
            // Handle featured image
            if (array_key_exists('featured_image', $data)) {
                // If featured_image is explicitly set in data (including NULL), use that value
                $stmt->bindValue(':featured_image', $data['featured_image'], PDO::PARAM_STR);
            } else {
                // Keep existing featured image if no change requested
                $stmt->bindValue(':featured_image', $existingPost['featured_image'], PDO::PARAM_STR);
            }
            
            $result = $stmt->execute();
            
            if ($result) {
                // Generate new sitemap
                try {
                    $sitemap = new Sitemap();
                    $sitemap->generateSitemapXML();
                } catch (Exception $sitemapError) {
                    if (getenv('MODE') === 'development') {
                        error_log("Sitemap regeneration failed after blog post update: " . $sitemapError->getMessage());
                    }
                    // Don't fail the whole operation if sitemap fails
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            if (getenv('MODE') === 'development') {
                error_log("Error updating blog post: " . $e->getMessage());
            }
            throw $e;
        }
    }
    
    public function deletePost($id) {
        requireAdminSession(true);
        try {
            // Get the post to retrieve the featured image path
            $post = $this->getPostById($id);

            $query = "DELETE FROM blog_posts WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            $result = $stmt->execute() && $stmt->rowCount() > 0;

            // Remove the featured image only after the row is actually gone
            if ($result && $post && $post['featured_image']) {
                $imagePath = __DIR__ . '/../' . $post['featured_image'];
                if (file_exists($imagePath)) {
                    unlink($imagePath); // Delete the featured image
                }
            }

            if ($result) {
                // Generate new sitemap after successful deletion
                try {
                    $sitemap = new Sitemap();
                    $sitemap->generateSitemapXML();
                } catch (Exception $sitemapError) {
                    if (getenv('MODE') === 'development') {
                        error_log("Sitemap regeneration failed after blog post deletion: " . $sitemapError->getMessage());
                    }
                    // Don't fail the whole operation if sitemap fails
                }
            }
            
            return $result;
        } catch (Exception $e) {
            if (getenv('MODE') === 'development') {
                error_log("Error deleting blog post: " . $e->getMessage());
            }
            return false;
        }
    }

    public function createSlug($text) {
        // Convert the text to lowercase and replace spaces with hyphens
        $slug = strtolower(str_replace(' ', '-', $text));
        
        // Remove any characters that aren't letters, numbers, or hyphens
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        
        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Remove leading and trailing hyphens
        $slug = trim($slug, '-');
        
        // If slug is empty (after cleaning), use a timestamp
        if (empty($slug)) {
            $slug = 'post-' . time();
        }
        
        // Check if slug already exists
        $query = "SELECT COUNT(*) FROM blog_posts WHERE slug = :slug";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        
        $count = $stmt->fetchColumn();
        
        // If slug exists, append a number
        if ($count > 0) {
            $i = 1;
            do {
                $newSlug = $slug . '-' . $i;
                $stmt->bindValue(':slug', $newSlug, PDO::PARAM_STR);
                $stmt->execute();
                $count = $stmt->fetchColumn();
                $i++;
            } while ($count > 0);
            
            $slug = $newSlug;
        }
        
        return $slug;
    }

    private function preparePostData(array $data): array {
        $data['title'] = trim((string)($data['title'] ?? ''));
        $data['content'] = $this->sanitizePostContent((string)($data['content'] ?? ''));
        $plainText = trim(preg_replace('/\s+/', ' ', strip_tags($data['content'])));

        if ($data['title'] === '') {
            throw new Exception('Title is required.');
        }

        $data['status'] = (($data['status'] ?? 'draft') === 'published') ? 'published' : 'draft';
        $data['slug'] = !empty($data['slug']) ? $this->sanitizeSlug((string)$data['slug']) : $this->createSlug($data['title']);
        $data['excerpt'] = trim((string)($data['excerpt'] ?? ''));
        $data['meta_description'] = trim((string)($data['meta_description'] ?? ''));
        $data['meta_keywords'] = trim((string)($data['meta_keywords'] ?? ''));

        if ($data['excerpt'] === '') {
            $data['excerpt'] = $this->summarizeText($plainText, 155);
        }
        if ($data['meta_description'] === '') {
            $data['meta_description'] = $this->summarizeText($data['title'] . '. ' . $plainText, 158);
        }
        if ($data['meta_keywords'] === '') {
            $data['meta_keywords'] = $this->generateKeywords($data['title'] . ' ' . $plainText);
        }

        return $data;
    }

    private function sanitizePostContent(string $content): string {
        $content = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $content);
        $content = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $content);
        $content = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $content);
        $content = preg_replace('/javascript\s*:/i', '', $content);
        $content = preg_replace('/<img\b([^>]*)>/i', '<img$1 loading="lazy">', $content);
        return $content;
    }

    private function sanitizeSlug(string $slug): string {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'post-' . time();
    }

    private function summarizeText(string $text, int $maxLength): string {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '' || strlen($text) <= $maxLength) {
            return $text;
        }
        $cut = substr($text, 0, $maxLength + 1);
        $space = strrpos($cut, ' ');
        if ($space !== false && $space > 80) {
            $cut = substr($cut, 0, $space);
        } else {
            $cut = substr($cut, 0, $maxLength);
        }
        return rtrim($cut, " \t\n\r\0\x0B.,;:!?-") . '...';
    }

    private function generateKeywords(string $text): string {
        $stopWords = ['about', 'after', 'again', 'also', 'and', 'because', 'been', 'before', 'being', 'between', 'from', 'have', 'into', 'just', 'more', 'over', 'that', 'the', 'their', 'there', 'this', 'through', 'with', 'your'];
        $source = strtolower(preg_replace('/[^a-z0-9\s-]/', ' ', $text));
        $counts = [];

        foreach (preg_split('/\s+/', $source) as $word) {
            if (strlen($word) < 4 || in_array($word, $stopWords, true)) {
                continue;
            }
            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }

        arsort($counts);
        return implode(', ', array_slice(array_keys($counts), 0, 8));
    }

    public function getPostById($id) {
        $query = "SELECT p.*, u.username as author_name 
                 FROM blog_posts p 
                 LEFT JOIN users u ON p.author_id = u.id 
                 WHERE p.id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return null; // Return null if no post found
        }
        return $result;
    }

    public function getTotalPublishedPosts() {
        $query = "SELECT COUNT(*) 
                 FROM blog_posts 
                 WHERE status = 'published'";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchColumn();
    }

    /**
     * Get paginated blog posts
     * 
     * @param int $offset Offset for pagination
     * @param int $limit Number of posts per page
     * @return array Array of blog posts
     */
    public function getPaginatedPosts($offset = 0, $limit = 10) {
        $query = "SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total number of blog posts
     * 
     * @return int Total number of posts
     */
    public function getTotalPosts() {
        $query = "SELECT COUNT(*) FROM blog_posts";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchColumn();
    }

    // Add this method for dashboard compatibility
    public function getRecentPosts($limit = 5) {
        $query = "SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Verify we're getting blog posts by checking for required fields
        if (!empty($posts) && !isset($posts[0]['title'])) {
            return [];
        }
        
        return $posts;
    }

    private function debug($message, $data = null) {
        $logMessage = "Blog Model Debug - " . $message;
        if ($data !== null) {
            $logMessage .= ": " . print_r($data, true);
        }
        if (getenv('MODE') === 'development') {
            error_log($logMessage);
        }
    }
}


