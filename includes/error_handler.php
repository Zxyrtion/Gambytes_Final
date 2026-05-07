<?php
/**
 * Custom Error Handler and Logging
 */

// Set error log file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $message = date('[Y-m-d H:i:s] ') . "Error [$errno]: $errstr in $errfile on line $errline\n";
    error_log($message, 3, __DIR__ . '/../logs/error.log');
    
    // Display user-friendly message in production
    if (defined('APP_ENV') && APP_ENV === 'production') {
        echo "An error occurred. Please try again later.";
    } else {
        echo "<b>Error:</b> $errstr in <b>$errfile</b> on line <b>$errline</b>";
    }
}

// Custom exception handler
function customExceptionHandler($exception) {
    $message = date('[Y-m-d H:i:s] ') . "Exception: " . $exception->getMessage() . 
               " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
    error_log($message, 3, __DIR__ . '/../logs/error.log');
    
    if (defined('APP_ENV') && APP_ENV === 'production') {
        echo "An error occurred. Please try again later.";
    } else {
        echo "<b>Exception:</b> " . $exception->getMessage();
    }
}

// Set handlers
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $userId = $_SESSION['user_id'] ?? 'anonymous';
    
    $message = date('[Y-m-d H:i:s] ') . "SECURITY: $event | User: $userId | IP: $ip | Details: $details | User-Agent: $userAgent\n";
    error_log($message, 3, __DIR__ . '/../logs/security.log');
}

/**
 * Log authentication events
 */
function logAuthEvent($event, $email = '', $success = true) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $status = $success ? 'SUCCESS' : 'FAILED';
    
    $message = date('[Y-m-d H:i:s] ') . "AUTH: $event | Email: $email | Status: $status | IP: $ip\n";
    error_log($message, 3, __DIR__ . '/../logs/auth.log');
}
?>