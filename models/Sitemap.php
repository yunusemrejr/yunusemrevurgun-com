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
            'travel' => '0.7',     // Travel map page
            'updates' => '0.6',    // Updates page
            'rmrp' => '0.6',       // Random memories page
            'contact' => '0.6',    // Contact page
            'music' => '0.6',      // Music page
            'videos' => '0.6',     // Videos page
            'downloads' => '0.6',  // Downloads page
            'yunobot' => '0.6',    // YunoBot AI assistant page
            'post-code' => '0.6',  // Post-code feed
            'science-corner' => '0.6', // Science Corner
            'comedy' => '0.5',     // Comedy page
            'more' => '0.5',       // More pages hub
            'sitemap' => '0.3',    // HTML sitemap page
            'privacy' => '0.3',    // Privacy policy
            'terms' => '0.3',      // Terms of service
            'cookies' => '0.3'     // Cookie policy
        ];

        foreach ($staticPages as $page => $priority) {
            // WHY: this was date('c') — every static URL claimed "modified right
            // now" on every request, which teaches crawlers to ignore lastmod
            // entirely. The view file's mtime is the honest signal.
            $this->addUrl($xml, $urlset, $page, $priority, $this->staticPageModified($page));
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

        // Add individual memories (RMRP). Guarded: the table is created lazily
        // by models/Rmrp.php on first use, so on a fresh deploy it may not
        // exist yet — skip the block instead of failing the whole sitemap.
        try {
            $query = "SELECT id,
                             COALESCE(updated_at, created_at) as lastmod
                      FROM rmrp_memories
                      ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $memories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($memories as $memory) {
                $this->addUrl($xml, $urlset, 'rmrp/' . $memory['id'], '0.6', $memory['lastmod']);
            }
        } catch (Exception $e) {
            error_log('Sitemap: rmrp_memories table not available yet: ' . $e->getMessage());
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

    /**
     * Real last-modified signal for a static route: the mtime of the view file
     * that renders it. $page comes from the hardcoded $staticPages map above,
     * so the path is never attacker-controlled. Returns null when no view file
     * resolves — addUrl then omits <lastmod> rather than inventing a date.
     */
    private function staticPageModified(string $page): ?string
    {
        $candidates = $page === '' ? ['home.php'] : [$page . '.php', $page . '/index.php', 'legal/' . $page . '.php'];
        foreach ($candidates as $candidate) {
            $file = __DIR__ . '/../views/' . $candidate;
            if (is_file($file)) {
                $mtime = filemtime($file);
                if ($mtime !== false) {
                    return date('c', $mtime);
                }
            }
        }
        return null;
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
        if ($path == 'rmrp') return 'weekly';
        if ($path == 'portfolio') return 'monthly';
        if ($path == 'gallery') return 'weekly';
        
        // Blog posts
        if (strpos($path, 'blog/') === 0) return 'monthly';
        
        // Individual updates
        if (strpos($path, 'updates/') === 0) return 'monthly';

        // Individual memories (RMRP)
        if (strpos($path, 'rmrp/') === 0) return 'monthly';
        
        // More pages
        if (in_array($path, ['post-code', 'science-corner', 'videos', 'downloads', 'travel', 'music', 'yunobot'])) return 'monthly';

        // Hub pages
        if (in_array($path, ['comedy', 'more'])) return 'monthly';

        // Legal/static pages
        if (in_array($path, ['privacy', 'terms', 'cookies', 'sitemap'])) return 'yearly';
        
        // Default for other pages
        return 'weekly';
    }
}