<?php
require_once dirname(__DIR__) . '/config/setPath.php';

class SessionAdminSecurityMiddleware {
    private $request;

    public function __construct($request) {
        $this->request = $request;
    }

    public function handle() {
        if (isAdminRequestUri($this->request['uri'])) {
            $requestPath = parse_url($this->request['uri'], PHP_URL_PATH);
            if (strpos($requestPath, '/admin/login') !== false || strpos($requestPath, '/admin/logout') !== false) {
                return true;
            }

            if (!isset($_SESSION['user_id'])) {
                header('Location: ' . getAdminLoginPath());
                exit;
            }

            if (in_array($this->request['method'], ['POST', 'DELETE'], true)) {
                $csrfToken = $_POST['csrf_token'] ?? '';

                if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Invalid CSRF token']);
                    exit;
                }
            }
        }
        return true;
    }
}
