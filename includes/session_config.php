<?php
/**
 * Secure Session Configuration
 * Include this instead of session_start() in all files
 */

// Prevent session fixation and configure security
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);  // Prevent JavaScript access
    ini_set('session.use_only_cookies', 1); // Only use cookies for sessions
    ini_set('session.cookie_secure', 0);    // Set to 1 for HTTPS (0 for localhost)
    ini_set('session.cookie_samesite', 'Strict'); // CSRF protection
    ini_set('session.use_strict_mode', 1);  // Reject uninitialized session IDs
    
    // Set session timeout (30 minutes)
    ini_set('session.gc_maxlifetime', 1800);
    ini_set('session.cookie_lifetime', 1800);
    
    session_start();
    
    // Check for session timeout
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
    
    // Regenerate session ID periodically — but NOT during AJAX/API requests
    // to avoid invalidating the session between page load and fetch calls
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
               || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;

    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } else if (!$is_ajax && time() - $_SESSION['CREATED'] > 300) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
}
?>