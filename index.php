<?php
require "src/bootstrap.php";
use Src;

header("Content-Type: application/json");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );

if ($uri[1] !== 'api' || !isset($uri[2]) || !isset($uri[3])) {
    // Check endpoint: fail
    showError("Invalid endpoint", 400);
} else {
    // Email
    $userId = $uri[2];

    // REST API name
    $function = $uri[3];

    // GET, POST, PUT, DELETE
    $requestMethod = $_SERVER["REQUEST_METHOD"];

    // Handle the API request
    $controller = new RequestHandler($dbConnection, $requestMethod, $userId, $function);
    $controller->processRequest();
    $controller->close();
}