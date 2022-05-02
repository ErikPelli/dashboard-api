<?php
require "src/bootstrap.php";
require "src/handlers.php";

header("Content-Type: application/json");

$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$uri = explode("/", $uri);

if ($uri[1] !== "api" || !isset($uri[2])) {
    // Check endpoint: fail
    Src\showError("Invalid endpoint", HTTP_BAD_REQUEST);
} else {
    // REST API name
    $function = $uri[3];

    // GET, POST, PUT, DELETE
    $requestMethod = $_SERVER["REQUEST_METHOD"];

    // Handle the API request
    $controller = new Src\RequestHandler($dbConnection, $requestMethod, $function);
    $controller->processRequest();
    $controller->close();
}
