<?php

namespace Src;

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

    private function parseJsonData(): string {
        return json_decode(file_get_contents('php://input'), true);
    }

    private function checkErrorThrowException() {
        $error = $this->db->error();
        if ($error) {
            throw new \Exception($error);
        }
    }

    protected function user(): mixed {
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
                throw new UnsupportedMethod();
        }
        return $result;
    }

    protected function settings(): mixed {
    }

    protected function noncompliances(): mixed {
    }

    protected function noncompliance(): mixed {
    }

    protected function tickets(): mixed {
    }

    protected function ticket(): mixed {
    }

    /**
     * Process an HTTP request and print the JSON result.
     */
    public function processRequest(): void {
        if (
            \method_exists($this, $this->function) and
            (new \ReflectionMethod($this, $this->function))->isProtected()
        ) {
            try {
                // Variable function
                $apiFunction = $this->function;
                $result = $apiFunction();
                if ($result == null) {
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

    /**
     * Clean current database connession.
     */
    public function close(): void {
        $this->db->close();
    }
}
