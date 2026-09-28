<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

// Include the setPath file using the project root
require_once $projectRoot . '/config/setPath.php';

 
class Router {
    private $routes = [
        '' => 'views/home.php',
        'home' => 'views/home.php',
        'about' => 'views/about.php',
        'portfolio' => 'views/portfolio.php',
        'contact' => 'views/contact.php',
        'gallery' => 'views/gallery.php',
        'blog' => 'views/blog.php',
        'updates' => 'views/updates.php',
        'rmrp' => 'views/rmrp.php',
        'travel' => 'views/travel.php',
        'post-code' => 'views/post-code/index.php',
        'science-corner' => 'views/science-corner/index.php',
        'yunobot' => 'views/yunobot/index.php',
        'gemmaclaim' => 'views/gemmaclaim/index.php',
        'videos' => 'views/videos/index.php',
        'downloads' => 'views/downloads/index.php',
        'slop' => 'views/slop/index.php',
        'more' => 'views/more.php',
        'admin/login' => 'views/admin/login.php',
        'admin' => 'views/admin/login.php',
        'admin/logout' => 'views/admin/logout.php',
        'admin/dashboard' => 'views/admin/dashboard.php',
        'admin/blog' => 'views/admin/blog/index.php',
        'admin/blog/create' => 'views/admin/blog/create.php',
        'admin/blog/edit' => 'views/admin/blog/edit.php',
        'admin/updates' => 'views/admin/updates/index.php',
        'admin/updates/create' => 'views/admin/updates/create.php',
        'admin/updates/edit' => 'views/admin/updates/edit.php',
        'admin/rmrp' => 'views/admin/rmrp/index.php',
        'admin/rmrp/create' => 'views/admin/rmrp/create.php',
        'admin/rmrp/edit' => 'views/admin/rmrp/edit.php',
        'admin/rmrp/delete' => 'views/admin/rmrp/delete.php',
        'admin/gallery' => 'views/admin/gallery/index.php',
        'admin/updates/delete' => 'views/admin/updates/delete.php',
        'admin/portfolio' => 'views/admin/portfolio/index.php',
        'admin/portfolio/create' => 'views/admin/portfolio/create.php',
        'admin/portfolio/edit' => 'views/admin/portfolio/edit.php',
        'admin/portfolio/delete' => 'views/admin/portfolio/delete.php',
        '404' => 'views/404.php',
        'sitemap' => 'views/sitemap.php',
        'sitemap.xml' => 'api/sitemap.php',
        'blog.xml' => 'api/blog-feed.php',
        'updates.xml' => 'api/updates-feed.php',
        'rmrp.xml' => 'api/rmrp-feed.php',
        'llms.txt' => 'api/llms.php',
        'privacy' => 'views/legal/privacy.php',
        'terms' => 'views/legal/terms.php',
        'cookies' => 'views/legal/cookies.php',
        'admin/js-check' => 'views/admin/js-check.php',
        'admin/settings' => 'views/admin/settings/index.php',

        'admin/tracker-codes' => 'views/admin/tracker-codes/index.php',
        'admin/tracker-codes/edit' => 'views/admin/tracker-codes/edit.php',
        'admin/tracker-codes/create' => 'views/admin/tracker-codes/create.php',
        'search' => 'views/search.php',
        'api/search' => 'api/search.php',
        'api/llms' => 'api/llms.php',
        'api/contact' => 'api/contact.php',
        'admin/search' => 'views/admin/search.php',
        'admin/api/search' => 'views/admin/api/search.php',
        'music' => 'views/music.php',
        'comedy' => 'views/comedy.php',
        'admin/music' => 'views/admin/music/index.php',
        'admin/travel' => 'views/admin/travel/index.php',
        'admin/videos' => 'views/admin/videos/index.php',
        'admin/downloads' => 'views/admin/downloads/index.php',
        'admin/slop' => 'views/admin/slop/index.php',
        'admin/socials' => 'views/admin/socials/index.php',
    ];

    public function route() {
        // Handle both development and production modes
        $isDevMode = (php_sapi_name() === 'cli-server' || getenv('MODE') === 'development');
        
        if ($isDevMode) {
            // Development mode: use REQUEST_URI
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            $url = parse_url($requestUri, PHP_URL_PATH);
            $url = trim($url, '/');
        } else {
            // Production mode: use GET parameter from .htaccess
            $url = $_GET['url'] ?? '';
            $url = trim($url, '/');
        }

        if (in_array($url, ['home', 'index', 'index.php'], true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            header('Location: ' . FULL_BASE_PATH, true, 301);
            return;
        }

        // Guard the resolved route too: a query-string route must not bypass URI middleware.
        if (($url === 'admin' || str_starts_with($url, 'admin/')) && $url !== 'admin/login') {
            require_once dirname(__DIR__) . '/includes/admin_request.php';
            guardAdminRequest(str_starts_with($url, 'admin/api/'));
        }

        // Handle root URL (empty string)
        if (empty($url)) {
            $url = '';
        }

        // Debug: Log the route being requested (development only)
        if (getenv('MODE') === 'development') {
            error_log("Router: Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
            error_log("Router: Parsed URL: " . $url);
        }

        // Check for lockdown mode (except for admin pages)
        if ($url !== 'admin' && !str_starts_with($url, 'admin/')) {
            require_once dirname(__DIR__) . '/models/Settings.php';
            $settings = new Settings();
            
            if ($settings->isLockdownModeEnabled()) {
                // Display lockdown page
                require_once dirname(__DIR__) . '/views/lockdown.php';
                return;
            }
        }

        // Special case for /admin - redirect to login if not authenticated
        if ($url === 'admin' || $url === 'admin/') {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Check if user is logged in
            if (!isset($_SESSION['user_id'])) {
                // In development mode, use relative path instead of FULL_BASE_PATH
                if (getenv('MODE') === 'development') {
                    header('Location: /admin/login');
                } else {
                    header('Location: ' . FULL_BASE_PATH . 'admin/login');
                }
                exit;
            }
        }

        // Check if this is a blog post URL - fixed regex pattern
        if (strpos($url, 'blog/') === 0) {  // First check if it starts with blog/
            $slug = substr($url, strlen('blog/')); // Get everything after blog/
            
            // Normalize: strip index.php, page/N, posts/ prefixes from malformed URLs
            $slug = preg_replace('#^index\.php/#', '', $slug);
            $slug = preg_replace('#^page/\d+/#', '', $slug);
            $slug = preg_replace('#^posts/#', '', $slug);
            $slug = preg_replace('#/index\.php/#', '/', $slug);
            $slug = preg_replace('#/page/\d+/#', '/', $slug);
            $slug = preg_replace('#/+/#', '/', $slug);
            $slug = trim($slug, '/');
            
            if (!empty($slug)) {
                require_once dirname(__DIR__) . '/models/Blog.php';
                $blog = new Blog();
                
                if (getenv('MODE') === 'development') {
                    error_log("Router: Processing blog post URL");
                    error_log("Full URL: " . $_SERVER['REQUEST_URI']);
                    error_log("Processed URL: " . $url);
                    error_log("Extracted slug: " . $slug);
                }
                
                // Get the published post
                $post = $blog->getPublishedPost($slug);
                
                if ($post) {
                    if (getenv('MODE') === 'development') {
                        error_log("Router: Found post with title: " . $post['title']);
                    }
                    $GLOBALS['current_post'] = $post;
                    require_once dirname(__DIR__) . '/views/blog-post.php';
                    return;
                }
                
                if (getenv('MODE') === 'development') {
                    error_log("Router: No post found with slug: " . $slug);
                }
                $notFoundPath = dirname(__DIR__) . '/' . $this->routes['404'];
                require_once $notFoundPath;
                return;
            }
        }

        // Check if this is an individual RMRP memory URL
        if (strpos($url, 'rmrp/') === 0) {
            $memoryId = substr($url, strlen('rmrp/')); // Get everything after rmrp/

            if (!empty($memoryId) && is_numeric($memoryId)) {
                require_once dirname(__DIR__) . '/models/Rmrp.php';
                $rmrp = new Rmrp();

                if (getenv('MODE') === 'development') {
                    error_log("Router: Processing individual memory URL");
                    error_log("Full URL: " . $_SERVER['REQUEST_URI']);
                    error_log("Processed URL: " . $url);
                    error_log("Extracted memory ID: " . $memoryId);
                }

                // Get the memory
                $memory = $rmrp->getMemoryById($memoryId);

                if ($memory) {
                    if (getenv('MODE') === 'development') {
                        error_log("Router: Found memory with title: " . $memory['title']);
                    }
                    $GLOBALS['current_rmrp'] = $memory;
                    require_once dirname(__DIR__) . '/views/rmrp-single.php';
                    return;
                }

                if (getenv('MODE') === 'development') {
                    error_log("Router: No memory found with ID: " . $memoryId);
                }
                $notFoundPath = dirname(__DIR__) . '/' . $this->routes['404'];
                require_once $notFoundPath;
                return;
            }
        }

        // Check if this is an individual update URL
        if (strpos($url, 'updates/') === 0) {
            $updateId = substr($url, strlen('updates/')); // Get everything after updates/
            
            if (!empty($updateId) && is_numeric($updateId)) {
                require_once dirname(__DIR__) . '/models/Updates.php';
                $updates = new Updates();
                
                if (getenv('MODE') === 'development') {
                    error_log("Router: Processing individual update URL");
                    error_log("Full URL: " . $_SERVER['REQUEST_URI']);
                    error_log("Processed URL: " . $url);
                    error_log("Extracted update ID: " . $updateId);
                }
                
                // Get the update
                $update = $updates->getUpdateById($updateId);
                
                if ($update) {
                    if (getenv('MODE') === 'development') {
                        error_log("Router: Found update with title: " . $update['title']);
                    }
                    $GLOBALS['current_update'] = $update;
                    require_once dirname(__DIR__) . '/views/update-single.php';
                    return;
                }
                
                if (getenv('MODE') === 'development') {
                    error_log("Router: No update found with ID: " . $updateId);
                }
                $notFoundPath = dirname(__DIR__) . '/' . $this->routes['404'];
                require_once $notFoundPath;
                return;
            }
        }

        // Check if this is an individual slop page URL
        if (strpos($url, 'slop/') === 0) {
            $slug = trim(substr($url, strlen('slop/')), '/');

            if ($slug !== '' && preg_match('#^[a-z0-9-]+$#', $slug)) {
                require_once dirname(__DIR__) . '/models/Slop.php';
                $slopModel = new Slop();

                if (getenv('MODE') === 'development') {
                    error_log("Router: Processing slop page URL, slug: " . $slug);
                }

                $slop = $slopModel->getSlopBySlug($slug);

                if ($slop) {
                    $GLOBALS['current_slop'] = $slop;
                    require_once dirname(__DIR__) . '/views/slop/single.php';
                    return;
                }
            }

            $notFoundPath = dirname(__DIR__) . '/' . $this->routes['404'];
            require_once $notFoundPath;
            return;
        }

        // Route to appropriate page FIRST (before including header/footer)
        // Handle admin pages early to prevent header/footer inclusion
        if (array_key_exists($url, $this->routes)) {
            // For admin pages, ensure proper routing
            if (str_starts_with($url, 'admin/') || $url === 'admin') {
                // Allow access to login without verification
                if ($url === 'admin/login' || $url === 'admin') {
                    $routePath = $this->routes[$url];
                    // Convert relative path to absolute if needed
                    if (!str_starts_with($routePath, '/') && !str_starts_with($routePath, dirname(__DIR__))) {
                        $routePath = dirname(__DIR__) . '/' . $routePath;
                    }
                    require_once $routePath;
                    return;
                }
                
                // For other admin pages, verify session
                if (!isset($_SESSION['user_id'])) {
                    // In development mode, use relative path instead of FULL_BASE_PATH
                    if (getenv('MODE') === 'development' || php_sapi_name() === 'cli-server') {
                        header('Location: /admin/login');
                    } else {
                        header('Location: ' . FULL_BASE_PATH . 'admin/login');
                    }
                    exit;
                }
            }
        }

        // Pages that manage their own layout via ui.php functions
        $selfLayoutPages = ['', 'home', 'about', 'portfolio', 'gallery', 'contact', 'travel', 'updates', 'rmrp', 'blog', 'post-code', 'science-corner', 'yunobot', 'gemmaclaim', 'videos', 'downloads', 'slop', 'search', 'privacy', 'terms', 'cookies', 'more', 'sitemap', 'llms', 'music'];
        $skipLayout = str_starts_with($url, 'admin/') || str_starts_with($url, 'api/') || in_array($url, $selfLayoutPages, true);

        // Layout is self-contained via ui.php functions; no separate header/footer files

        // Continue routing for non-admin pages
        if (array_key_exists($url, $this->routes)) {
            $routePath = $this->routes[$url];
            if (!str_starts_with($routePath, '/') && !str_starts_with($routePath, dirname(__DIR__))) {
                $routePath = dirname(__DIR__) . '/' . $routePath;
            }
            require_once $routePath;
        } else {
            require_once dirname(__DIR__) . '/views/404.php';
        }


    }


}

