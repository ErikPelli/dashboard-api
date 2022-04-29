<?php

namespace Src;

class RequestHandler {
    private $db;
    private $requestMethod;
    private $function;
    private $data;

    public function __construct($db, $requestMethod, $function) {
        $this->db = new DatabaseHandler($db);
        $this->requestMethod = $requestMethod;
        $this->function = $function;
        $this->data = $this->parseJsonData();
    }

    private function parseJsonData() {
        return json_decode(file_get_contents('php://input'));
    }

    private function user() {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Get user informatiom
            case HTTP_POST:
                // Login
            case HTTP_PUT:
                // Register
                $fiscalCode = $this->data["fiscalCode"];
                $firstName = $this->data["firstName"];
                $lastName = $this->data["lastName"];
                $email = $this->data["email"];
                $username = $this->data["username"];
                $password = $this->data["password"];

                $result = $this->db->registerUser($fiscalCode, $firstName, $lastName, $email, $username, $password);
                $error = $this->db->getError();
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
        }
    }

    public function processRequest() {
    }

    public function close() {
        $this->db->close();
    }
}
