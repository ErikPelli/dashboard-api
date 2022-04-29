<?php

namespace Src;

use Exception;

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
        return json_decode(file_get_contents('php://input'), true);
    }

    protected function user() {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Get user informatiom
            case HTTP_POST:
                // Login
            case HTTP_PUT:
                // Register
                $full = $this->data["fullname"];
                $email = $this->data["email"];
                $username = $this->data["username"];
                $password = $this->data["password"];

                $result = $this->db->registerUser($full, $email, $username, $password);
                $error = $this->db->error();
                if ($error) {
                    throw new Exception($error, HTTP_BAD_REQUEST);
                }
                return $result;
        }
    }

    public function processRequest() {
        if (
            \method_exists($this, $this->function) and
            (new \ReflectionMethod($this, $this->function))->isProtected()
        ) {
            // Variable function
            try {
                $result = $this->$this->function();
                if ($result == null) {
                    showError("Method not supported", HTTP_BAD_REQUEST);
                } else {
                    showResult($result);
                }
            } catch (Exception $e) {
                showError($e->getMessage(), $e->getCode());
            }
        }
    }

    public function close() {
        $this->db->close();
    }
}
