<?php
// Get the project root directory - use absolute path resolution
$projectRoot = dirname(__DIR__);

// Include the setPath file using the project root
require_once $projectRoot . '/config/setPath.php';
 
// If global.php is not included, include it and restrict direct access
if (file_exists('../global.php')) {
    require_once '../global.php';
    restrictDirectAccess();
}  


class SessionAdminSecurityMiddleware {
    private $request;

    public function __construct($request) {
        $this->request = $request;
    }

    public function handle() {
        // Only check for admin session authentication
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

             if (in_array($this->request['method'], ['POST', 'DELETE'], true)) {
                $csrfToken = $_POST['csrf_token'] ?? $this->request['headers']['X-CSRF-Token'] ?? $this->request['body']['csrf_token'] ?? '';

                if (!$this->isValidCsrfToken($csrfToken)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Invalid CSRF token']);
                    exit;
                }
            }
        }
        return true;
    }

    private function isValidCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
