<?php

namespace Src;

/**
 * RequestHandler is a class that handles an HTTP request to the /api REST APIs.
 * To use it, initialize a new object with the right parameters in the constructor
 * and then call the processRequest method.
 * 
 * @package Src
 * @author Erik Pellizzon <https://github.com/ErikPelli>
 * @author Ilias El Ikhbari <https://github.com/BlackJekko>
 * @access public
 */
class RequestHandler {
    private DatabaseHandler $db;
    private string $requestMethod;
    private string $function;
    private array $data;

    /**
     * Initialize a new instance of the RequestHandler.
     */
    public function __construct(\mysqli $db, string $requestMethod, string $function) {
        $this->db = new DatabaseHandler($db);
        $this->requestMethod = $requestMethod;
        $this->function = $function;
        $this->data = $this->parseJsonData();
    }

    /**
     * Parse input JSON data into an associative array.
     * @return array, the parsed associative array.
     */
    private function parseJsonData(): array {
        return json_decode(file_get_contents('php://input'), true);
    }

    /**
     * Check if there is a DB error.
     * @throws Exception if there is an error.
     */
    private function checkErrorThrowException(): void {
        $error = $this->db->error();
        if ($error) {
            throw new \Exception($error);
        }
    }

    /**
     * Check if the JSON input contains the required fields in keys array.
     * @param keys string array
     * @throws InvalidArgumentException if required key is not present.
     */
    private function jsonKeysOK(array $keys): void {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->data)) {
                throw new \InvalidArgumentException("Invalid JSON input parameters");
            }
        }
    }

    /**
     * Handle the /api/user REST endpoint.
     * 
     * Get data about a specific user:
     *  GET /api/user
     *    {
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result":
     *    }
     * 
     * Check login data of an existent user:
     *  POST /api/user
     *    {
     *        "email": string,
     *        "password": string
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {"exists": bool} | {}
     *    }
     * 
     * Register a new user:
     *  PUT /api/user
     *    { 
     *        "fiscalCode": string,
     *        "firstName: string,
     *        "lastName": string,
     *        "email": string,
     *        "password": string
     *   }
     *  Result:
     *   {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {}
     *    }
     * 
     * @return mixed any value that will encoded into JSON "result" field.
     * @throws UnsupportedMethodException current REST method not supported.
     */
    protected function user(): mixed {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Get user information
                $this->jsonKeysOK(array("email"));
                // Result array keys: firstName, lastName, fiscalCode
                $result = $this->db->getInfoUser($this->data["email"]);
                $this->checkErrorThrowException();
                break;
            case HTTP_POST:
                // Login
                $this->jsonKeysOK(array("email", "password"));
                $exists = $this->db->userExists($this->data["email"], $this->data["password"]);
                $this->checkErrorThrowException();
                $result = array("exists" => $exists);
                break;
            case HTTP_PUT:
                // Register
                $this->jsonKeysOK(array("fiscalCode", "firstName", "lastName", "email", "password"));
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
                throw new UnsupportedMethodException();
        }
        return $result;
    }

    protected function password(): mixed {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Check if password is set
                $this->jsonKeysOK(array("email"));
                $set = $this->db->isPasswordSet($this->data["email"]);
                $this->checkErrorThrowException();
                $result = array("isSet" => $set);
                break;
            case HTTP_POST:
                // Set new password
                $this->jsonKeysOK(array("email", "password"));
                $this->db->setPassword($this->data["email"], $this->data["password"]);
                $this->checkErrorThrowException();
                $result = null;
                break;
            case HTTP_DELETE:
                // Reset password
                $this->jsonKeysOK(array("email"));
                $this->db->setPassword($this->data["email"]);
                $this->checkErrorThrowException();
                $result = null;
                break;
            default:
                throw new UnsupportedMethodException();
        }
        return $result;
    }

    protected function settings(): mixed {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Get current settings (department, job, role)
                $this->jsonKeysOK(array("email"));
                $result = $this->db->getInfoSettings($this->data["email"]);
                $this->checkErrorThrowException();
                break;
            case HTTP_POST:
                // Set new settings
                $this->jsonKeysOK(array("email"));
                $options = array();
                if(array_key_exists("job", $this->data)) {
                    $this->data["job"] = $this->data["job"];
                }
                if(array_key_exists("role", $this->data)) {
                    $this->data["role"] = $this->data["role"];
                }
                if(array_key_exists("company", $this->data)) {
                    $this->data["company"] = $this->data["company"];
                }
                $this->db->setInfoSettings($this->data["email"], $options);
                $result = null;
                break;
            case HTTP_DELETE:
                // Set default settings
                $this->jsonKeysOK(array("email"));
                $this->db->setInfoSettings($this->data["email"], array(
                    "job" => $_ENV['DEFAULT_JOB'],
                    "role" => $_ENV['DEFAULT_ROLE'],
                    "department" => $_ENV['DEFAULT_DEPARTMENT']
                ));
                $result = null;
                break;
            default:
                throw new UnsupportedMethodException();
        }
        return $result;
    }

    protected function noncompliances(): mixed {
        // GET Get non compliances list
        // POST get current non compliances stats (new, in progress, review, closed). get status numbers for every day last month.
        // PUT Get available non compliance types
    }

    protected function noncompliance(): mixed {
        // GET available non compliance types
        // PUT add new noncompliance instance
        // POST details about a noncompliance instance
    }

    protected function tickets(): mixed {
        // GET all tickets
        // POST get open tickets for every day last month. + Currently today not closed tickets.
    }

    protected function ticket(): mixed {
        // GET ticket details
        // POST answer to ticket
        // DELETE close ticket
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
                $result = $this->$apiFunction();
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
