<?php
/**
 * URL Helper Functions
 * Provides dynamic URL generation to avoid hardcoded paths
 */

/**
 * Get the base URL for the application
 */
function getBaseUrl() {
    if (defined('BASE_URL')) {
        return BASE_URL;
    }
    
    // Auto-detect base URL if not defined
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    
    // Remove common paths to get to root
    $scriptPath = str_replace(['/app/views/auth', '/app/views/Users/Gamblers', '/app/views/Users/Supervisor', '/api'], '', $scriptPath);
    
    return $protocol . '://' . $host . $scriptPath;
}

/**
 * Generate URL for assets (CSS, JS, images)
 */
function asset($path) {
    return getBaseUrl() . '/public/' . ltrim($path, '/');
}

/**
 * Generate URL for application routes
 */
function url($path) {
    return getBaseUrl() . '/' . ltrim($path, '/');
}

/**
 * Generate URL for API endpoints
 */
function apiUrl($path) {
    return getBaseUrl() . '/api/' . ltrim($path, '/');
}

/**
 * Redirect to a URL
 */
function redirect($path) {
    header('Location: ' . url($path));
    exit();
}

/**
 * Get current URL
 */
function currentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    
    return $protocol . '://' . $host . $uri;
}
?>