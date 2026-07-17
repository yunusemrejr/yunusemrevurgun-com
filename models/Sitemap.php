<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

require_once dirname(__DIR__) . '/config/setPath.php';
 
class Sitemap {
    private $db;
    private $baseUrl;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->baseUrl = $this->getBaseUrl();
    }

    private function getBaseUrl() {
        // Dynamically determine base URL based on environment
        if (getenv('MODE') === 'development') {
            // Development mode - use FULL_BASE_PATH if available, otherwise build from SERVER vars
            if (defined('FULL_BASE_PATH')) {
                return rtrim(FULL_BASE_PATH, '/');
            }
            
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            
            // If HTTP_HOST doesn't include port, try to add it from SERVER_PORT
            if ($host === 'localhost' && !strpos($host, ':')) {
                $port = $_SERVER['SERVER_PORT'] ?? ($protocol === 'https://' ? '443' : '80');
                if (($protocol === 'http://' && $port !== '80') || ($protocol === 'https://' && $port !== '443')) {
                    $host .= ':' . $port;
                }
            }
            
            return rtrim($protocol . $host, '/');
        } else {
            // Production mode - use the configured domain
            if (defined('FULL_BASE_PATH')) {
                return rtrim(FULL_BASE_PATH, '/');
            }
            // Fallback to production domain
            return 'https://yunusemrevurgun.com';
        }
    }

    public function generateSitemapXML() {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Create urlset element
        $urlset = $xml->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->appendChild($urlset);

        // Add static pages with comprehensive main pages
        $staticPages = [
            '' => '1.0',           // Home page - highest priority
            'about' => '0.8',      // About page
            'portfolio' => '0.9',  // Portfolio - high priority for professional site
            'blog' => '0.8',       // Blog listing page
            'gallery' => '0.7',    // Gallery page
            'updates' => '0.6',    // Updates page
            'contact' => '0.6',    // Contact page
            'sitemap' => '0.3',    // HTML sitemap page
            'post-code' => '0.6',  // Post-code feed
            'science-corner' => '0.6', // Science Corner
            'videos' => '0.6',   // Videos page
            'privacy' => '0.3',    // Privacy policy
            'terms' => '0.3',      // Terms of service
            'cookies' => '0.3'     // Cookie policy
        ];

        foreach ($staticPages as $page => $priority) {
            // Add lastmod date for static pages (use current time for now)
            $this->addUrl($xml, $urlset, $page, $priority, date('c'));
        }

        // Add blog posts
        $query = "SELECT slug, 
                         COALESCE(updated_at, created_at) as lastmod 
                  FROM blog_posts 
                  WHERE status = 'published' 
                  ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as $post) {
            $this->addUrl($xml, $urlset, 'blog/' . $post['slug'], '0.7', $post['lastmod']);
        }

        // Add individual updates
        $query = "SELECT id, 
                         COALESCE(updated_at, created_at) as lastmod 
                  FROM updates 
                  ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($updates as $update) {
            $this->addUrl($xml, $urlset, 'updates/' . $update['id'], '0.6', $update['lastmod']);
        }

        // Guardrail: never overwrite the tracked sitemap files from a local
        // request — localhost URLs would poison the prod sitemap on commit.
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (php_sapi_name() === 'cli'
            || strpos($host, 'localhost') !== false
            || strpos($host, '127.0.0.1') !== false
            || strpos($host, '192.168.') !== false) {
            return true;
        }

        // Save the sitemap to both locations for compatibility
        $publicPath = __DIR__ . '/../public/sitemap.xml';
        $rootPath = __DIR__ . '/../sitemap.xml';
        
        // Ensure directories exist
        $publicDir = dirname($publicPath);
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }
        
        // Save to both locations
        $xml->save($publicPath);
        $xml->save($rootPath);
        
        return true;
    }
    
    /**
     * Force regenerate sitemap - useful for admin operations
     */
    public function forceRegenerate() {
        try {
            return $this->generateSitemapXML();
        } catch (Exception $e) {
            error_log("Sitemap force regeneration failed: " . $e->getMessage());
            return false;
        }
    }

    private function addUrl($xml, $urlset, $path, $priority, $lastmod = null) {
        $url = $xml->createElement('url');
        
        // Add location
        $loc = $xml->createElement('loc');
        $fullUrl = $this->baseUrl . '/' . ltrim($path, '/');
        $loc->appendChild($xml->createTextNode($fullUrl));
        $url->appendChild($loc);
        
        // Add last modified date if available
        if ($lastmod) {
            $lastmodElement = $xml->createElement('lastmod');
            $lastmodElement->appendChild($xml->createTextNode(date('c', strtotime($lastmod))));
            $url->appendChild($lastmodElement);
        }
        
        // Add priority
        $priorityElement = $xml->createElement('priority');
        $priorityElement->appendChild($xml->createTextNode($priority));
        $url->appendChild($priorityElement);
        
        // Add changefreq
        $changefreq = $xml->createElement('changefreq');
        $changefreq->appendChild($xml->createTextNode($this->getChangeFreq($path)));
        $url->appendChild($changefreq);
        
        $urlset->appendChild($url);
    }

    private function getChangeFreq($path) {
        // Home and main pages
        if ($path == '' || $path == 'home') return 'daily';
        if ($path == 'blog') return 'daily';
        if ($path == 'updates') return 'weekly';
        if ($path == 'portfolio') return 'monthly';
        if ($path == 'gallery') return 'weekly';
        
        // Blog posts
        if (strpos($path, 'blog/') === 0) return 'monthly';
        
        // Individual updates
        if (strpos($path, 'updates/') === 0) return 'monthly';
        
        // More pages
        if (in_array($path, ['post-code', 'science-corner', 'videos'])) return 'monthly';

        // Legal/static pages
        if (in_array($path, ['privacy', 'terms', 'cookies', 'sitemap'])) return 'yearly';
        
        // Default for other pages
        return 'weekly';
    }
}