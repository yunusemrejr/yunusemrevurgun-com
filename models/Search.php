<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

require_once dirname(__DIR__) . '/config/setPath.php';

 
// Check if Database class is already defined before requiring it
if (!class_exists('Database')) {
    require_once __DIR__ . '/Database.php';
}

class Search {
    private $db;
    private $maxQueryLength = 60; // Reduced maximum allowed search query length to 60

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Sanitize and validate search query
     * 
     * @param string $query The search query
     * @return string|false Sanitized query or false if invalid
     */
    public function sanitizeSearchQuery($query) {
        // Trim whitespace
        $query = trim($query);
        
        // Check if query is empty after trimming
        if (empty($query)) {
            return false;
        }
        
        // Check query length
        if (strlen($query) > $this->maxQueryLength) {
            return false;
        }
        
        // Remove any potentially harmful characters - only allow letters, numbers and single spaces
        $query = preg_replace('/[^\p{L}\p{N} ]/u', '', $query);
        
        // Replace multiple spaces with a single space
        $query = preg_replace('/\s+/', ' ', $query);
        
        // Ensure query is still valid after sanitization
        if (strlen($query) < 2) {
            return false;
        }
        
        return $query;
    }

    /**
     * Search for blog posts
     * 
     * @param string $query The search query
     * @param bool $adminMode Whether to include unpublished posts (admin only)
     * @return array The search results
     */
    public function searchBlogPosts($query, $adminMode = false) {
        // Sanitize query for public searches
        if (!$adminMode) {
            $query = $this->sanitizeSearchQuery($query);
            if ($query === false) {
                return [];
            }
        }
        
        $searchTerm = "%$query%";
        
        $sql = "SELECT id, title, slug, excerpt, created_at, status 
                FROM blog_posts 
                WHERE (title LIKE :query OR content LIKE :query OR excerpt LIKE :query)";
        
        // For public search, only show published posts
        if (!$adminMode) {
            $sql .= " AND status = 'published'";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 50"; // Limit results for security
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':query', $searchTerm, PDO::PARAM_STR);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert status to is_published for compatibility with existing code
        foreach ($results as &$result) {
            $result['is_published'] = ($result['status'] === 'published');
            
            // Sanitize output for public searches
            if (!$adminMode) {
                $result['title'] = htmlspecialchars($result['title']);
                $result['excerpt'] = htmlspecialchars($result['excerpt']);
                // Truncate long fields
                $result['excerpt'] = substr($result['excerpt'], 0, 200);
            }
        }
        
        return $results;
    }

    /**
     * Search for updates
     * 
     * @param string $query The search query
     * @param bool $adminMode Whether to include unpublished updates (admin only)
     * @return array The search results
     */
    public function searchUpdates($query, $adminMode = false) {
        // Sanitize query for public searches
        if (!$adminMode) {
            $query = $this->sanitizeSearchQuery($query);
            if ($query === false) {
                return [];
            }
        }
        
        $searchTerm = "%$query%";
        
        $sql = "SELECT id, title, description as content, update_date, created_at, importance, category 
                FROM updates 
                WHERE (title LIKE :query OR description LIKE :query OR category LIKE :query)";
        
        // No published/draft status in updates table based on schema
        // We'll treat all updates as published
        
        $sql .= " ORDER BY update_date DESC LIMIT 50"; // Limit results for security
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':query', $searchTerm, PDO::PARAM_STR);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add is_published field for compatibility
        foreach ($results as &$result) {
            $result['is_published'] = true;
            
            // Sanitize output for public searches
            if (!$adminMode) {
                $result['title'] = htmlspecialchars($result['title']);
                $result['content'] = htmlspecialchars($result['content']);
                // Truncate long fields
                $result['content'] = substr($result['content'], 0, 200);
            }
        }
        
        return $results;
    }

    /**
     * Search for portfolio items
     * 
     * @param string $query The search query
     * @param bool $adminMode Whether to include hidden portfolio items (admin only)
     * @return array The search results
     */
    public function searchPortfolio($query, $adminMode = false) {
        // Sanitize query for public searches
        if (!$adminMode) {
            $query = $this->sanitizeSearchQuery($query);
            if ($query === false) {
                return [];
            }
        }
        
        $searchTerm = "%$query%";
        
        $sql = "SELECT id, title, description, image as image_url, created_at, featured 
                FROM portfolio_projects 
                WHERE (title LIKE :query OR description LIKE :query OR technologies LIKE :query)";
        
     
        
        $sql .= " ORDER BY created_at DESC LIMIT 50"; // Limit results for security
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':query', $searchTerm, PDO::PARAM_STR);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert featured to is_visible for compatibility
        foreach ($results as &$result) {
            $result['is_visible'] = (bool)$result['featured'];
            
            // Sanitize output for public searches
            if (!$adminMode) {
                $result['title'] = htmlspecialchars($result['title']);
                $result['description'] = htmlspecialchars($result['description']);
                // Truncate long fields
                $result['description'] = substr($result['description'], 0, 200);
            }
        }
        
        return $results;
    }

    /**
     * Perform a global search across all content types
     * 
     * @param string $query The search query
     * @param bool $adminMode Whether to include unpublished/hidden content (admin only)
     * @return array The search results grouped by content type
     */
    public function globalSearch($query, $adminMode = false) {
        return [
            'blog' => $this->searchBlogPosts($query, $adminMode),
            'updates' => $this->searchUpdates($query, $adminMode),
            'portfolio' => $this->searchPortfolio($query, $adminMode)
        ];
    }

    /**
     * Format search results for display
     * 
     * @param array $results The search results
     * @param string $type The content type (blog, updates, portfolio)
     * @param bool $adminMode Whether to format for admin panel
     * @return array Formatted results with URLs and additional info
     */
    public function formatSearchResults($results, $type, $adminMode = false) {
        $formattedResults = [];
        
        foreach ($results as $item) {
            $formattedItem = $item;
            
            // Add URL based on content type
            switch ($type) {
                case 'blog':
                    $formattedItem['url'] = $adminMode 
                        ? FULL_BASE_PATH . "admin/blog/edit?id={$item['id']}" 
                        : FULL_BASE_PATH . "blog/{$item['slug']}";
                    break;
                    
                case 'updates':
                    $formattedItem['url'] = $adminMode 
                        ? FULL_BASE_PATH . "admin/updates/edit?id={$item['id']}" 
                        : FULL_BASE_PATH . "updates";
                    break;
                    
                case 'portfolio':
                    $formattedItem['url'] = $adminMode 
                        ? FULL_BASE_PATH . "admin/portfolio/edit?id={$item['id']}" 
                        : FULL_BASE_PATH . "portfolio";
                    break;
            }
            
            // Add content type for reference
            $formattedItem['content_type'] = $type;
            
            $formattedResults[] = $formattedItem;
        }
        
        return $formattedResults;
    }
}

