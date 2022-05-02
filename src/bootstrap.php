<?php
require '../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Create needed tables
$initsql = file_get_contents("init.sql");
if ($initsql !== false) {
    if ($dbConnection->query($initsql) === false) {
        echo "Invalid query result";
        exit();
    }
}
