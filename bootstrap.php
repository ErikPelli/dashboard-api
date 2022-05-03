<?php
require 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize database connection
mysqli_report(MYSQLI_REPORT_STRICT);
try {
    $dbConnection = new mysqli(
        $_ENV['DB_HOST'],
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        $_ENV['DB_DATABASE'],
        (int) $_ENV['DB_PORT']
    );
} catch (Exception $e) {
    require_once("src/handlers.php");
    Src\showError("Unable to connect to database", HTTP_BAD_REQUEST);
    exit();
}

// Create needed tables
$initsql = @file_get_contents("init.sql");
if ($initsql !== false) {
    if ($dbConnection->query($initsql) === false) {
        echo "Invalid query result";
        exit();
    }
}
