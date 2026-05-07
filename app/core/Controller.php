<?php
/**
 * Base Controller Class
 * All controllers should extend this class
 */
class Controller {
    
    /**
     * Load a view file
     * @param string $view - View file name (without .php extension)
     * @param array $data - Data to pass to the view
     */
    protected function view($view, $data = []) {
        // Extract data array to variables
        extract($data);
        
        // Build view path
        $viewPath = __DIR__ . "/../views/" . $view . ".php";
        
        // Check if view exists
        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {
            die("View not found: " . $view);
        }
    }
    
    /**
     * Load a model
     * @param string $model - Model name
     * @return object - Model instance
     */
    protected function model($model) {
        // Build model path
        $modelPath = __DIR__ . "/../models/" . $model . ".php";
        
        // Check if model exists
        if (file_exists($modelPath)) {
            require_once $modelPath;
            return new $model();
        } else {
            die("Model not found: " . $model);
        }
    }
    
    /**
     * Redirect to another page
     * @param string $url - URL to redirect to
     */
    protected function redirect($url) {
        header("Location: " . $url);
        exit();
    }
    
    /**
     * Return JSON response
     * @param array $data - Data to return as JSON
     * @param int $statusCode - HTTP status code
     */
    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
    
    /**
     * Check if user is authenticated
     * @return bool
     */
    protected function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Require authentication
     * Redirects to login if not authenticated
     */
    protected function requireAuth() {
        if (!$this->isAuthenticated()) {
            $this->redirect('/app/views/auth/login.php');
        }
    }
    
    /**
     * Check if user has specific role
     * @param string|array $roles - Role(s) to check
     * @return bool
     */
    protected function hasRole($roles) {
        if (!isset($_SESSION['role'])) {
            return false;
        }
        
        if (is_array($roles)) {
            return in_array($_SESSION['role'], $roles);
        }
        
        return $_SESSION['role'] === $roles;
    }
    
    /**
     * Require specific role
     * @param string|array $roles - Required role(s)
     */
    protected function requireRole($roles) {
        if (!$this->hasRole($roles)) {
            die("Access denied. Insufficient permissions.");
        }
    }
}
