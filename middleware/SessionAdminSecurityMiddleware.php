<?php
require_once dirname(__DIR__) . '/includes/admin_request.php';

class SessionAdminSecurityMiddleware {
    public function __construct(private array $request) {}

    public function handle(): bool {
        if (!isAdminRequestUri($this->request['uri'])) return true;
        $path = rtrim((string)parse_url($this->request['uri'], PHP_URL_PATH), '/');
        $login = rtrim((string)parse_url(getAdminLoginPath(), PHP_URL_PATH), '/');
        if ($path === $login) return true;
        guardAdminRequest(str_contains($path, '/api/'));
        return true;
    }
}
