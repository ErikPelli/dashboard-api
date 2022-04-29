<?php

namespace Src;

use Exception;

class RequestHandler {
    private DatabaseHandler $db;
    private string $requestMethod;
    private string $function;
    private array $data;

    public function __construct(\mysqli $db, string $requestMethod, string $function) {
        $this->db = new DatabaseHandler($db);
        $this->requestMethod = $requestMethod;
        $this->function = $function;
        $this->data = $this->parseJsonData();
    }

    private function parseJsonData() : string {
        return json_decode(file_get_contents('php://input'), true);
    }

    protected function user() : mixed {
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
                $error = $this->db->error();
                if ($error) {
                    throw new Exception($error, HTTP_BAD_REQUEST);
                }
                return $result;
        }
    }

    public function processRequest() : void {
        if (
            \method_exists($this, $this->function) and
            (new \ReflectionMethod($this, $this->function))->isProtected()
        ) {
            try {
                // Variable function
                $apiFunction = $this->function;
                $result = $apiFunction();
                if ($result == null) {
                    showError("Method not supported", HTTP_METHOD_NOT_ALLOWED);
                } else {
                    showResult($result);
                }
            } catch (Exception $e) {
                showError($e->getMessage(), $e->getCode());
            }
        }
    }

    public function close() : void {
        $this->db->close();
    }
}
