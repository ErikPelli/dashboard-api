<?php

namespace Src;

class UnsupportedMethod {}

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

    private function checkErrorThrowException() {
        $error = $this->db->error();
        if ($error) {
            throw new \Exception($error);
        }
    }

    protected function user() : mixed {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Get user information
            case HTTP_POST:
                // Login
                $exists = $this->db->userExists(
                    $this->data["email"],
                    $this->data["password"]
                );
                $this->checkErrorThrowException();
                $result = array("exists" => $exists);
                break;
            case HTTP_PUT:
                // Register
                $this->db->registerUser(
                    $this->data["fiscalCode"],
                    $this->data["firstName"],
                    $this->data["lastName"],
                    $this->data["email"],
                    $this->data["password"]
                );
                $this->checkErrorThrowException();
                $result = null;
                break;
            default:
                $result = new UnsupportedMethod(); 
        }
        return $result;
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
                if ($result instanceof UnsupportedMethod) {
                    showError("Method not supported", HTTP_METHOD_NOT_ALLOWED);
                } else if ($result == null) {
                    showResult();
                } else {
                    showResult($result);
                }
            } catch (\Exception $e) {
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
