<?php
require_once __DIR__ . '/../config/setPath.php';

 
require_once __DIR__ . '/Database.php';

class Auth {
    private $db;
    private $maxAttempts = 5; // Maximum login attempts
    private $lockoutTime = 300; // Lockout time in seconds (5 minutes)

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login($username, $password) {
        try {
            // Initialize login attempts if not set
            if (!isset($_SESSION['login_attempts'])) {
                $_SESSION['login_attempts'] = 0;
                $_SESSION['lockout_time'] = null;
            }

            // Check if the user is locked out
            if ($_SESSION['lockout_time'] && time() < $_SESSION['lockout_time']) {
                error_log("User is locked out until " . date('Y-m-d H:i:s', $_SESSION['lockout_time']));
                return false; // User is locked out
            }

            if (getenv('MODE') === 'development') {
                error_log("Login attempt for username: " . $username);
            }
            
            $query = "SELECT id, username, password FROM users WHERE username = :username";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                if (getenv('MODE') === 'development') {
                    error_log("Password verified successfully for user: " . $username);
                }
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['last_activity'] = time();
                $_SESSION['admin_user_agent'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                $_SESSION['login_attempts'] = 0;
                return true;
            }
            
            // Increment login attempts
            $_SESSION['login_attempts']++;
            if (getenv('MODE') === 'development') {
                error_log("Login failed for username: " . $username);
            }

            // Lock the user out if max attempts reached
            if ($_SESSION['login_attempts'] >= $this->maxAttempts) {
                $_SESSION['lockout_time'] = time() + $this->lockoutTime; // Set lockout time
                if (getenv('MODE') === 'development') {
                    error_log("User locked out due to too many failed attempts.");
                }
            }

            return false;
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            throw $e;
        }
    }

    public static function checkLogin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user is logged in and session is valid
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            self::redirectToLoginIfWeb();
            return false;
        }

        $currentAgent = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (isset($_SESSION['admin_user_agent']) && !hash_equals($_SESSION['admin_user_agent'], $currentAgent)) {
            session_destroy();
            self::redirectToLoginIfWeb();
            return false;
        }

        // Check session timeout (30 minutes)
        if (time() - $_SESSION['last_activity'] > 1800) {
            session_destroy();
            self::redirectToLoginIfWeb(true);
            return false;
        }

        // Update last activity time
        $_SESSION['last_activity'] = time();
        return true;
    }

    private static function redirectToLoginIfWeb($expired = false) {
        if (php_sapi_name() === 'cli' || headers_sent()) {
            return;
        }
        $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        if ($isAjax || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            return;
        }
        $suffix = $expired ? '?expired=1' : '';
        header('Location: ' . FULL_BASE_PATH . 'admin/login' . $suffix);
        exit;
    }

    public function logout() {
        // Clear the session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        header('Location: ' . FULL_BASE_PATH . 'admin/login');
        exit;
    }

    public function verifyAdminPageAccess() {
        // Skip for login page
        if (strpos($_SERVER['REQUEST_URI'], FULL_BASE_PATH . 'admin/login') !== false) {
            return;
        }

        // Check for token in various places
        $pageToken = $_SERVER['HTTP_X_ADMIN_VERIFY'] ?? $_GET['admin_token'] ?? null;
        $sessionToken = $_SESSION['admin_page_token'] ?? null;

        if (!$pageToken || !$sessionToken || $pageToken !== $sessionToken) {
            error_log('Admin access denied: Invalid or missing token');
            http_response_code(403);
            include __DIR__ . '/403.php';
            exit;
        }
    }

    /**
     * Check if a user is logged in
     * 
     * @return bool True if logged in, false otherwise
     */
    public static function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_user_agent'])) {
            return false;
        }
        $currentUA = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (!hash_equals($_SESSION['admin_user_agent'], $currentUA)) {
            return false;
        }
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
            unset($_SESSION['user_id']);
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
}



// If global.php is not included, include it and restrict direct access
if (file_exists('../global.php')) {
    require_once '../global.php';
    restrictDirectAccess();
}
