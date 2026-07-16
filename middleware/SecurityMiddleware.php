<?php
require_once dirname(__DIR__) . '/config/setPath.php';

class SecurityMiddleware {
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
        }
        return true;
    }
}
