<?php
// Get the project root directory - use absolute path resolution
$projectRoot = dirname(__DIR__);

// Include the setPath file using the project root
require_once $projectRoot . '/config/setPath.php';
class SecurityMiddleware {
    private $request;

    public function __construct($request) {
        $this->request = $request;
    }

    public function handle() {
        // Only check for basic session authentication
        if (isAdminRequestUri($this->request['uri'])) {
            // Skip check for login and logout pages (compare paths, not full URLs)
            $requestPath = parse_url($this->request['uri'], PHP_URL_PATH);
            if (strpos($requestPath, '/admin/login') !== false || strpos($requestPath, '/admin/logout') !== false) {
                return true;
            }

            // Check session for other admin pages
            if (!isset($_SESSION['user_id'])) {
                header('Location: ' . getAdminLoginPath());
                exit;
            }
        }
        return true;
    }

    private function enforceAdminSecurity() {
        return true;
    }

    private function sendError($code, $message) {
        http_response_code($code);
        include __DIR__ . '/../403.php';
        exit;
    }

    private function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}  

// If global.php is not included, include it and restrict direct access
if (file_exists('../global.php')) {
    require_once '../global.php';
    restrictDirectAccess();
}  