<?php
/**
 * Router Class
 * Handles URL routing and dispatching to controllers
 */
class Router {
    private $controller = 'AuthController';
    private $method = 'login';
    private $params = [];
    
    /**
     * Constructor - Parse URL and route to controller
     */
    public function __construct() {
        $url = $this->parseUrl();
        
        // Check for controller
        if (isset($url[0]) && !empty($url[0])) {
            $controllerName = ucfirst($url[0]) . 'Controller';
            $controllerPath = __DIR__ . '/../controllers/' . $controllerName . '.php';
            
            if (file_exists($controllerPath)) {
                $this->controller = $controllerName;
                unset($url[0]);
            }
        }
        
        // Require controller file
        $controllerPath = __DIR__ . '/../controllers/' . $this->controller . '.php';
        if (file_exists($controllerPath)) {
            require_once $controllerPath;
        } else {
            die("Controller not found: " . $this->controller);
        }
        
        // Instantiate controller
        $this->controller = new $this->controller;
        
        // Check for method
        if (isset($url[1]) && !empty($url[1])) {
            if (method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
                unset($url[1]);
            }
        }
        
        // Get parameters
        $this->params = $url ? array_values($url) : [];
        
        // Call controller method with parameters
        call_user_func_array([$this->controller, $this->method], $this->params);
    }
    
    /**
     * Parse URL from GET parameter
     * @return array
     */
    private function parseUrl() {
        if (isset($_GET['url'])) {
            return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
        }
        return [];
    }
    
    /**
     * Add route (for future expansion)
     * @param string $route
     * @param string $controller
     * @param string $method
     */
    public function addRoute($route, $controller, $method = 'index') {
        // Future implementation for custom routes
    }
}
