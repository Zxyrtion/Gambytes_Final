<?php
session_start();

// Autoload core, controllers, models
spl_autoload_register(function ($class) {
    if (file_exists("../app/core/" . $class . ".php")) {
        require_once "../app/core/" . $class . ".php";
    } elseif (file_exists("../app/controllers/" . $class . ".php")) {
        require_once "../app/controllers/" . $class . ".php";
    } elseif (file_exists("../app/models/" . $class . ".php")) {
        require_once "../app/models/" . $class . ".php";
    }
});

// Default values
$controllerName = "AuthController";
$method = "login";
$params = [];

// Get URL
$url = isset($_GET['url']) ? explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL)) : [];

// Controller
if (!empty($url[0])) {
    $controllerName = ucfirst($url[0]) . "Controller";
}

// Method
if (!empty($url[1])) {
    $method = $url[1];
}

// Params
$params = array_slice($url, 2);

// Check if controller exists
if (!class_exists($controllerName)) {
    die("Controller not found.");
}

$controller = new $controllerName;

// Check if method exists
if (!method_exists($controller, $method)) {
    die("Method not found.");
}

// Call controller method with parameters
call_user_func_array([$controller, $method], $params);