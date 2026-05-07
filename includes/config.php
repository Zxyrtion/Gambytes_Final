<?php
// Load environment variables
require_once __DIR__ . '/env_loader.php';

// Set default values for any missing constants
if (!defined('CALENDLY_API_KEY')) {
    define('CALENDLY_API_KEY', 'your-calendly-api-key');
}
if (!defined('CALENDLY_API_URL')) {
    define('CALENDLY_API_URL', 'https://api.calendly.com');
}
if (!defined('CALENDLY_USER_URI')) {
    define('CALENDLY_USER_URI', '');
}
if (!defined('CALENDLY_ORGANIZATION_URI')) {
    define('CALENDLY_ORGANIZATION_URI', '');
}
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'gambytes');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '/GAMBYTES_Final');
}
if (!defined('APP_ENV')) {
    define('APP_ENV', 'development');
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', true);
}
?>
