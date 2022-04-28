<?php
namespace Src;

$functionHandlers = [
    "utente" => array(
        "GET" => function(RequestHandler $r) {
            // Get user information

        },
        "POST" => function(RequestHandler $r) {
            // Register
            $full = $r->data["fullname"];
            $email = $r->data["email"];
            $username = $r->data["username"];
            $password = $r->data["password"];

            $result = $r->db->registerUser($full, $email, $username, $password);
            $error = $r->db->getError();
            if($error) {
                showError($error, 406);
            } else {
                http_response_code(200);
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
