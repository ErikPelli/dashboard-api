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
                $result = $this->db->registerUser(
                    $this->data["fiscalCode"],
                    $this->data["firstName"],
                    $this->data["lastName"],
                    $this->data["email"],
                    $this->data["password"]
                );
                $error = $this->db->error();
                if ($error) {
                    throw new Exception($error);
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
                // BAD_REQUEST is default HTTP error
                $code = $e->getCode();
                showError($e->getMessage(), ($code != 0) ? $code : HTTP_BAD_REQUEST);
            }
        }
    }

    public function close() : void {
        $this->db->close();
    }
}
