<?php

namespace Src;

use stdClass;

define('HTTP_SUCCESS', 200);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_NOT_ACCEPTABLE', 406);

define('HTTP_GET', "GET");
define('HTTP_POST', "POST");
define('HTTP_PUT', "PUT");
define('HTTP_DELETE', "DELETE");

function showError(string $message, int $statusCode) {
    http_response_code($statusCode);
    $error = array(
        "success" => false,
        "error" => $message,
        "result" => array(),
    );
    echo json_encode($error, JSON_FORCE_OBJECT);
}

function showResult($value = new stdClass()) {
    http_response_code(HTTP_SUCCESS);
    $error = array(
        "success" => true,
        "result" => $value,
    );
    echo json_encode($error);
}
