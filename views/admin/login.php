<?php
$projectRoot = dirname(__DIR__);

require_once dirname(__DIR__, 2) . '/config/setPath.php';

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    $secureSession = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureSession,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once dirname(__DIR__, 2) . '/global.php';
require_once dirname(__DIR__, 2) . '/models/Auth.php';
require_once __DIR__ . '/includes/verify.php';

function getLoginClientKey(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', $ip . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
}

function loadLoginGuardData(): array {
    $file = dirname(__DIR__, 2) . '/rate_limit_storage.json';
    if (!is_file($file)) {
        return ['admin_login' => []];
    }
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) {
        return ['admin_login' => []];
    }
    $data['admin_login'] = $data['admin_login'] ?? [];
    return $data;
}

function saveLoginGuardData(array $data): void {
    $file = dirname(__DIR__, 2) . '/rate_limit_storage.json';
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function getLoginDelaySeconds(array $record): int {
    $failures = (int)($record['failures'] ?? 0);
    if ($failures < 2) {
        return 0;
    }
    return min(30, 2 ** min(5, $failures - 1));
}

function isLoginTemporarilyBlocked(): bool {
    $data = loadLoginGuardData();
    $key = getLoginClientKey();
    $record = $data['admin_login'][$key] ?? [];
    $lastFailure = (int)($record['last_failure'] ?? 0);
    $delay = getLoginDelaySeconds($record);
    return $delay > 0 && (time() - $lastFailure) < $delay;
}

function recordLoginFailure(): void {
    $data = loadLoginGuardData();
    $key = getLoginClientKey();
    $record = $data['admin_login'][$key] ?? ['failures' => 0, 'last_failure' => 0];
    $record['failures'] = min(12, (int)$record['failures'] + 1);
    $record['last_failure'] = time();
    $data['admin_login'][$key] = $record;

    foreach ($data['admin_login'] as $recordKey => $storedRecord) {
        if (time() - (int)($storedRecord['last_failure'] ?? 0) > 86400) {
            unset($data['admin_login'][$recordKey]);
        }
    }

    saveLoginGuardData($data);
}

function clearLoginFailures(): void {
    $data = loadLoginGuardData();
    unset($data['admin_login'][getLoginClientKey()]);
    saveLoginGuardData($data);
}

/**
 * Minify HTML content to make it harder to read in browser dev tools
 * NOTE: This is a safe minifier that preserves script/style content
 */
function minifyHTML($buffer) {
    // Skip minification for development mode or if buffer is empty
    if (getenv('MODE') === 'development' || empty($buffer)) {
        return $buffer;
    }

    // Extract and protect script/style blocks
    $scripts = [];
    $styles = [];
    
    // Protect script blocks
    $buffer = preg_replace_callback('/<script\b[^>]*>(.*?)<\/script>/is', function($m) use (&$scripts) {
        $key = '<!--SCRIPT_PLACEHOLDER_' . count($scripts) . '-->';
        $scripts[$key] = $m[0];
        return $key;
    }, $buffer);
    
    // Protect style blocks
    $buffer = preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function($m) use (&$styles) {
        $key = '<!--STYLE_PLACEHOLDER_' . count($styles) . '-->';
        $styles[$key] = $m[0];
        return $key;
    }, $buffer);

    // Now safely minify HTML (scripts/styles are protected)
    $search = [
        '/\>[^\S ]+/s',     // Remove whitespace after >
        '/[^\S ]+\</s',     // Remove whitespace before <
        '/(\s)+/s',         // Collapse multiple spaces
        '/<!--(?!SCRIPT_|STYLE_)(.|\s)*?-->/', // Remove comments (except placeholders)
    ];
    $replace = ['>', '<', ' ', ''];
    $buffer = preg_replace($search, $replace, $buffer);

    // Restore script blocks
    foreach ($scripts as $key => $script) {
        $buffer = str_replace($key, $script, $buffer);
    }
    
    // Restore style blocks
    foreach ($styles as $key => $style) {
        $buffer = str_replace($key, $style, $buffer);
    }

    return $buffer;
}

// Start output buffering with minification callback
if (getenv('MODE') !== 'development') {
    ob_start('minifyHTML');

    // Add additional obfuscation headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
 
    // Add cache control to prevent caching of sensitive pages
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * Generate a simple math CAPTCHA challenge
 * @return array ['question' => string, 'answer' => int, 'hash' => string]
 */
function generateCaptcha() {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $operation = rand(0, 1); // 0 = addition, 1 = subtraction
    
    if ($operation === 0) {
        $answer = $num1 + $num2;
        $question = "$num1 + $num2";
    } else {
        // Ensure positive result
        if ($num1 < $num2) {
            $temp = $num1;
            $num1 = $num2;
            $num2 = $temp;
        }
        $answer = $num1 - $num2;
        $question = "$num1 - $num2";
    }
    
    // Create a hash of the answer with a session-based secret
    if (!isset($_SESSION['captcha_secret'])) {
        $_SESSION['captcha_secret'] = bin2hex(random_bytes(16));
    }
    $hash = hash_hmac('sha256', (string)$answer, $_SESSION['captcha_secret']);
    
    return [
        'question' => $question,
        'answer' => $answer,
        'hash' => $hash
    ];
}

/**
 * Verify CAPTCHA answer
 * @param string $userAnswer The user's answer
 * @param string $answerHash The hash of the correct answer
 * @return bool True if correct, false otherwise
 */
function verifyCaptcha($userAnswer, $answerHash) {
    if (!isset($_SESSION['captcha_secret']) || !isset($_SESSION['captcha_answer_hash'])) {
        return false;
    }
    
    // Verify the hash matches the session hash first
    if (!hash_equals($_SESSION['captcha_answer_hash'], $answerHash)) {
        return false;
    }
    
    // Get the correct answer from session (we'll store it temporarily)
    // Since we can't reverse the hash, we'll verify by checking if the user's answer matches
    $userAnswerInt = (int)trim($userAnswer);
    
    // We need to verify the answer matches what was generated
    // Since we hash the answer, we'll verify by checking if the hash of user's answer matches
    $userAnswerHash = hash_hmac('sha256', (string)$userAnswerInt, $_SESSION['captcha_secret']);
    
    return hash_equals($answerHash, $userAnswerHash);
}

// Generate CAPTCHA if not already set or if regenerating
if (!isset($_SESSION['captcha_question']) || isset($_GET['refresh_captcha'])) {
    $captcha = generateCaptcha();
    $_SESSION['captcha_question'] = $captcha['question'];
    $_SESSION['captcha_answer_hash'] = $captcha['hash'];
    $_SESSION['captcha_issued_at'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $captchaValid = false;
    $error = '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    $formStartedAt = (int)($_POST['form_started_at'] ?? 0);
    $elapsed = $formStartedAt > 0 ? time() - $formStartedAt : 0;

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        $error = "Security token expired. Please refresh and try again.";
        recordLoginFailure();
    } elseif (!empty($_POST['website'])) {
        $error = "Security verification failed. Please try again.";
        recordLoginFailure();
    } elseif ($elapsed > 0 && $elapsed < 2) {
        $error = "Please wait a moment and try again.";
        recordLoginFailure();
    } elseif (isLoginTemporarilyBlocked()) {
        $error = "Too many failed attempts. Please wait briefly and try again.";
    }
    
    // Check if CAPTCHA answer is present
    if (empty($error) && (!isset($_POST['captcha_answer']) || empty(trim($_POST['captcha_answer'])))) {
        $error = "Please complete the security verification.";
        $captchaValid = false;
    } 
    // Verify CAPTCHA answer
    elseif (empty($error) && (!isset($_SESSION['captcha_answer_hash']) || !isset($_POST['captcha_hash']) || (time() - (int)($_SESSION['captcha_issued_at'] ?? 0)) > 900)) {
        $error = "Security verification expired. Please refresh the page.";
        $captchaValid = false;
        // Regenerate CAPTCHA
        $captcha = generateCaptcha();
        $_SESSION['captcha_question'] = $captcha['question'];
        $_SESSION['captcha_answer_hash'] = $captcha['hash'];
        $_SESSION['captcha_issued_at'] = time();
    }
    elseif (empty($error)) {
        $userAnswer = trim($_POST['captcha_answer']);
        $answerHash = $_POST['captcha_hash'];
        
        // Verify the hash matches
        if (hash_equals($_SESSION['captcha_answer_hash'], $answerHash)) {
            $captchaValid = verifyCaptcha($userAnswer, $answerHash);
        } else {
            $captchaValid = false;
        }
        
        if (!$captchaValid) {
            $error = "Incorrect answer. Please try again.";
            recordLoginFailure();
            // Regenerate CAPTCHA on failure
            $captcha = generateCaptcha();
            $_SESSION['captcha_question'] = $captcha['question'];
            $_SESSION['captcha_answer_hash'] = $captcha['hash'];
            $_SESSION['captcha_issued_at'] = time();
        }
    }
    
    // Proceed with login if CAPTCHA verification passed
    if ($captchaValid) {
        try {
            require_once dirname(__DIR__, 2) . '/models/Database.php';
            $db = Database::getInstance()->getConnection();
            
            $auth = new Auth();
            $loginResult = $auth->login($_POST['username'], $_POST['password']);
            
            if ($loginResult) {
                clearLoginFailures();
                CSRFProtection::rotateToken();
                unset($_SESSION['captcha_question'], $_SESSION['captcha_answer_hash'], $_SESSION['captcha_issued_at']);
                header('Location: ' . FULL_BASE_PATH . 'admin/dashboard');
                exit;
            } else {
                recordLoginFailure();
                $error = "Invalid username or password";
            }
        } catch (Exception $e) {
            $error = "An error occurred during login";
            // Log exception in development mode
            if (getenv('MODE') === 'development') {
                error_log("Login exception: " . $e->getMessage());
            }
        }
    }
}

$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($currentPath, '/admin') !== false && 
    strpos($currentPath, '/admin/login') === false && 
    strpos($currentPath, '/admin/logout') === false &&
    !isset($_SESSION['user_id'])) {
    header('Location: ' . FULL_BASE_PATH . 'admin/login');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1E518F">
    <meta name="description" content="Admin Login - Yunus Emre Vurgun Personal Website Administration">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login | Yunus Emre Vurgun</title>
    <link id="appFavicon32" rel="icon" type="image/png" sizes="32x32" href="<?php echo FULL_BASE_PATH; ?>assets/images/favicon-pfp-32.png">
    <link id="appFavicon16" rel="icon" type="image/png" sizes="16x16" href="<?php echo FULL_BASE_PATH; ?>assets/images/favicon-pfp-32.png">
    <link id="appFaviconShortcut" rel="shortcut icon" href="<?php echo FULL_BASE_PATH; ?>assets/images/favicon-pfp.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo FULL_BASE_PATH; ?>assets/images/favicon-pfp.png">
    <meta name="msapplication-TileImage" content="<?php echo FULL_BASE_PATH; ?>assets/images/favicon-pfp.png">

    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">

    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/variables.css">
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/base.css">
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin-login.css">

    <?php if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
</head>
<body class="admin-login-body">
    <noscript>
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #1E518F; color: #FEFEFE; display: flex; align-items: center; justify-content: center; z-index: 9999;">
            <div style="text-align: center; padding: 2rem;">
                <h1 style="font-family: 'Space Grotesk', 'Inter', sans-serif;">JavaScript Required</h1>
                <p>This page requires JavaScript to function properly.</p>
                <p>Please enable JavaScript in your browser and refresh the page.</p>
                <a href="<?php echo FULL_BASE_PATH; ?>403.php?error=javascript_required" style="color: rgba(234,234,234,0.7); text-decoration: underline;">Click here if you cannot enable JavaScript</a>
            </div>
        </div>
    </noscript>
    
    <script>
        window.jsEnabled = true;
        document.addEventListener('DOMContentLoaded', function() {
            const noscriptElements = document.querySelectorAll('noscript');
            noscriptElements.forEach(function(element) {
                element.style.display = 'none';
            });
        });
    </script>
    
    <!-- Main Page Container -->
    <div class="admin-login-container">
        <!-- Site Title -->
        <div class="admin-login-identity">
            <img src="<?php echo FULL_BASE_PATH; ?>assets/images/favicon-pfp.png" alt="" class="admin-login-avatar" loading="eager">
            <p class="admin-login-kicker">Private console</p>
            <h1 class="admin-login-title">Admin Login</h1>
        </div>

        <!-- Login Form Container -->
        <div class="admin-login-form-container">

            <?php if (isset($error)): ?>
                <div class="admin-alert admin-alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['expired'])): ?>
                <div class="admin-alert admin-alert-warning">
                    <i class="bi bi-clock"></i>
                    Your session has expired. Please log in again.
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo FULL_BASE_PATH; ?>admin/login" id="admin-login-form" class="admin-login-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="form_started_at" value="<?php echo time(); ?>">
                    <div class="admin-login-trap" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    
                    <div class="admin-form-group">
                        <label for="username" class="admin-form-label">
                            <i class="bi bi-person"></i> Username
                        </label>
                        <input type="text" 
                               class="admin-form-control" 
                               id="username" 
                               name="username" 
                               placeholder="admin_user" 
                               required 
                               autocomplete="username">
                    </div>

                    <div class="admin-form-group">
                        <label for="password" class="admin-form-label">
                            <i class="bi bi-lock"></i> Password
                        </label>
                        <div class="admin-password-container">
                            <input type="password" 
                                   class="admin-form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="••••••••••••" 
                                   required 
                                   autocomplete="current-password">
                            <button type="button" class="admin-password-toggle" id="password-toggle" aria-label="Toggle password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="admin-form-group">
                        <label for="captcha_answer" class="admin-form-label">
                            <i class="bi bi-shield-check"></i> Security Verification
                        </label>
                        <div class="captcha-container">
                            <div class="captcha-question">
                                <?php echo htmlspecialchars($_SESSION['captcha_question'] ?? '? + ?'); ?> = ?
                            </div>
                            <button type="button" id="refresh-captcha" class="admin-btn admin-btn-secondary admin-btn-sm" title="Get a new question" aria-label="Refresh CAPTCHA">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <input type="number" 
                               class="admin-form-control" 
                               id="captcha_answer" 
                               name="captcha_answer" 
                               placeholder="Enter answer" 
                               required 
                               autocomplete="off"
                               min="0"
                               max="20"
                               style="max-width: 200px; margin-top: 0.5rem;">
                        <input type="hidden" name="captcha_hash" value="<?php echo htmlspecialchars($_SESSION['captcha_answer_hash'] ?? ''); ?>">
                        <small class="admin-text-muted" style="display: block; margin-top: 0.5rem; font-size: 0.875rem;">
                            Please solve the math problem above
                        </small>
                    </div>

                <button type="submit" class="admin-btn admin-btn-primary admin-btn-lg admin-login-submit">
                    <span class="btn-text">Login</span>
                    <span class="btn-loading">
                        <i class="bi bi-arrow-repeat"></i>
                        Processing...
                    </span>
                </button>
            </form>
        </div>
    </div>

    <!-- Component Scripts -->
    <script>
        // Set global base path for JS
        window.FULL_BASE_PATH = <?php echo json_encode(FULL_BASE_PATH); ?>;
    </script>
    
    <script src="<?php echo FULL_BASE_PATH; ?>assets/js/admin-login.js"></script>



    <?php
    // Flush and minify output buffer
    if (getenv('MODE') !== 'development') {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }
    ?>
</body>
</html> 
