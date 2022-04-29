<?php

namespace Src;

use stdClass;

define('HTTP_SUCCESS', 200);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_NOT_ACCEPTABLE', 406);

$functionHandlers = [
    "utente" => array(
        "GET" => function (RequestHandler $r) {
            // Get user information

        },
        "POST" => function (RequestHandler $r) {
            // Register
            $full = $r->data["fullname"];
            $email = $r->data["email"];
            $username = $r->data["username"];
            $password = $r->data["password"];

            $result = $r->db->registerUser($full, $email, $username, $password);
            $error = $r->db->getError();
            if ($error) {
                showError($error, HTTP_NOT_ACCEPTABLE);
            } else {
                http_response_code(HTTP_SUCCESS);
                $error = array(
                    "success" => true,
                    "result" => $result,
                );
                echo json_encode($error, JSON_FORCE_OBJECT);
            }
        },
    ),
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

function showResult($value = new stdClass()) {
    http_response_code(HTTP_SUCCESS);
    $error = array(
        "success" => true,
        "result" => $value,
    );
    echo json_encode($error);
}
