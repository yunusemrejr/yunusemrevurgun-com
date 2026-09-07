<?php
// Define the log file path with absolute path
$logFile = __DIR__ . '/../error_log.txt';

// Make sure the log file exists and is writable
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0666);
}

// Test write to the log file
$testWrite = @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " Error logging initialized\n", FILE_APPEND);
if ($testWrite === false) {
    // If we can't write to the log file, try to create a log in the system temp directory
    $logFile = sys_get_temp_dir() . '/personalwebsite_error.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " Fallback error logging initialized\n", FILE_APPEND);
}

// Create custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    global $logFile;

    // Format the error message
    $errorMessage = date('[Y-m-d H:i:s]') . " PHP Error [$errno]: $errstr in $errfile on line $errline" . PHP_EOL;

    // Log to file
    file_put_contents($logFile, $errorMessage, FILE_APPEND);

    // Output to console/terminal if in development mode or if running from CLI
    if (getenv('MODE') === 'development' || php_sapi_name() === 'cli') {
        $consoleMessage = "\033[1;31m[ERROR]\033[0m " . date('H:i:s') . " PHP Error [$errno]: $errstr\n";
        $consoleMessage .= "\033[0;33m       File:\033[0m $errfile:$errline\n";

        // Output to STDERR for terminal display
        file_put_contents('php://stderr', $consoleMessage);
    }

    // Don't execute PHP's internal error handler
    return true;
}

// Create custom exception handler
function customExceptionHandler($exception) {
    if (!headers_sent()) http_response_code(500);
    global $logFile;

    // Format the exception message
    $errorMessage = date('[Y-m-d H:i:s]') . " Uncaught Exception: " . $exception->getMessage() .
                   " in " . $exception->getFile() . " on line " . $exception->getLine() .
                   PHP_EOL . "Stack trace: " . PHP_EOL . $exception->getTraceAsString() . PHP_EOL;

    // Log to file
    file_put_contents($logFile, $errorMessage, FILE_APPEND);

    // Output to console/terminal if in development mode or CLI
    if (getenv('MODE') === 'development' || php_sapi_name() === 'cli') {
        $consoleMessage = "\033[1;31m[EXCEPTION]\033[0m " . date('H:i:s') . " Uncaught Exception: " . $exception->getMessage() . "\n";
        $consoleMessage .= "\033[0;33m           File:\033[0m " . $exception->getFile() . ":" . $exception->getLine() . "\n";

        // Output to STDERR for terminal display
        file_put_contents('php://stderr', $consoleMessage);

        // Show stack trace in terminal
        $consoleMessage .= "\033[0;36m           Stack trace:\033[0m\n" . $exception->getTraceAsString() . "\n";
        file_put_contents('php://stderr', $consoleMessage);
    } else {
        // Display a user-friendly message for production
        echo "An error occurred. Please try again later.";
    }

    exit(1);
}

// Create shutdown function to catch fatal errors
function shutdownHandler() {
    global $logFile;
    $error = error_get_last();

    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Format the error message
        $errorMessage = date('[Y-m-d H:i:s]') . " Fatal Error [{$error['type']}]: {$error['message']} in {$error['file']} on line {$error['line']}" . PHP_EOL;

        // Log to file
        file_put_contents($logFile, $errorMessage, FILE_APPEND);

        // Output to console/terminal if in development mode or CLI
        if (getenv('MODE') === 'development' || php_sapi_name() === 'cli') {
            $consoleMessage = "\033[1;35m[FATAL ERROR]\033[0m " . date('H:i:s') . " Fatal Error [{$error['type']}]: {$error['message']}\n";
            $consoleMessage .= "\033[0;33m             File:\033[0m {$error['file']}:{$error['line']}\n";

            // Output to STDERR for terminal display
            file_put_contents('php://stderr', $consoleMessage);
        }
    }
}

// Set the error handlers
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');
register_shutdown_function('shutdownHandler');

// Configure PHP error settings
if (getenv('MODE') === 'development' || php_sapi_name() === 'cli') {
    ini_set('display_errors', 1); // Show errors in development/CLI
    ini_set('display_startup_errors', 1); // Show startup errors
} else {
    ini_set('display_errors', 0); // Don't display errors to users in production
}

ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', $logFile); // Set the error log file
error_reporting(E_ALL); // Report all PHP errors
