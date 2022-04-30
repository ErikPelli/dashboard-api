<?php

namespace Src;

define('HTTP_SUCCESS', 200);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_METHOD_NOT_ALLOWED', 405);
define('HTTP_NOT_ACCEPTABLE', 406);

define('HTTP_GET', "GET");
define('HTTP_POST', "POST");
define('HTTP_PUT', "PUT");
define('HTTP_DELETE', "DELETE");

/**
 * Result to return in API handlers when the method is not defined.
 */
class UnsupportedMethodException extends \Exception {
    public function __construct() {
        parent::__construct("Method not supported", HTTP_METHOD_NOT_ALLOWED);
    }
}

function showError(string $message, int $statusCode): void {
    http_response_code($statusCode);
    $error = array(
        "success" => false,
        "error" => $message,
        "result" => array(),
    );
    echo json_encode($error, JSON_FORCE_OBJECT);
}

function showResult(mixed $value = new \stdClass()): void {
    http_response_code(HTTP_SUCCESS);
    $error = array(
        "success" => true,
        "result" => $value,
    );
    echo json_encode($error);
}
