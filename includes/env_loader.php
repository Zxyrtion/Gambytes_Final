<?php
/**
 * Load environment variables from .env file
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found at: ' . $path);
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Skip empty lines
        if (empty(trim($line))) {
            continue;
        }
        
        // Parse line
        if (strpos($line, '=') === false) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Set as environment variable
        putenv("$name=$value");
        
        // Define constant only if not already defined
        if (!defined($name)) {
            define($name, $value);
        }
    }
}

// Load .env file
try {
    loadEnv(__DIR__ . '/../.env');
} catch (Exception $e) {
    // Fallback to default values if .env not found
    error_log('Warning: ' . $e->getMessage());
}
?>