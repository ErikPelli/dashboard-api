<?php
namespace Src;

const functionHandlers = [

];

function showError(string $message, int $statusCode) {
    http_response_code($statusCode);
    $error = array(
        "success" => false,
        "error" => $message,
        "result" => array(),
    );
    echo json_encode($error, JSON_FORCE_OBJECT);
}
