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
        $decoded = json_decode(file_get_contents('php://input'), true);
        if ($decoded === null) {
            showError("Invalid JSON input", HTTP_BAD_REQUEST);
            exit();
        } else {
            return $decoded;
        }
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
     * Handle the /api/details REST endpoint.
     * 
     * Get a list of all the users:
     *  GET /api/details
     *    {}
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": [
     *                      {
     *                          "email": string
     *                      }
     *                  ] | {}
     *    }
     * 
     * Get a list of shipping lots, use limit=true to limit the results:
     *  POST /api/details
     *    {
     *        "limit": bool,
     *        "resultsPerPage": int | undefined,
     *        "pageNumber": int | undefined
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": [
     *                      {
     *                          "shippingCode": string,
     *                          "deliveryDate": string (YYYY-MM-DD)
     *                      }
     *                  ] | {}
     *    }
     * 
     * @return mixed any value that will encoded into JSON "result" field.
     * @throws UnsupportedMethodException current REST method not supported.
     */
    protected function details(): mixed {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Get all users
                $result = $this->db->getUsers();
                $this->checkErrorThrowException();
                break;
            case HTTP_POST:
                // Get shipping lots
                $this->jsonKeysOK(array("limit"));
                if ($this->data["limit"]) {
                    $this->jsonKeysOK(array("resultsPerPage", "pageNumber"));
                    $result = $this->db->getLots(true, $this->data["resultsPerPage"], $this->data["pageNumber"]);
                } else {
                    $result = $this->db->getLots(false);
                }
                $this->checkErrorThrowException();
                break;
            default:
                throw new UnsupportedMethodException();
        }
        return $result;
    }

    /**
     * Handle the /api/user REST endpoint.
     * 
     * Get data about a specific user:
     *  GET /api/user
     *    {
     *        "email": string
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {
     *                      "firstName": string,
     *                      "lastName": string,
     *                      "fiscalCode": string
     *                  } | {}
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
     *        "result": {
     *                      "exists": bool
     *                  } | {}
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

    /**
     * Handle the /api/password REST endpoint.
     * 
     * Check if password is set (if it has been reset, result is false):
     *  GET /api/password
     *    {
     *        "email": string
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {
     *                      "isSet": bool
     *                  } | {}
     *    }
     * 
     * Set a new password:
     *  POST /api/password
     *    {
     *        "email": string,
     *        "password": string
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {}
     *    }
     * 
     * Reset the current password:
     *  DELETE /api/password
     *    { 
     *        "email": string
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

    /**
     * Handle the /api/settings REST endpoint.
     * 
     * Get current settings for the specified user:
     *  GET /api/settings
     *    {
     *        "email": string
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {
     *                      "job": string,
     *                      "role": string,
     *                      "company": string
     *                  } | {}
     *    }
     * 
     * Overwrite current settings with some specified:
     *  POST /api/settings
     *    {
     *        "email": string,
     *        "job": string | undefined,
     *        "role": string | undefined,
     *        "company": string | undefined
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {}
     *    }
     * 
     * Reset the settings and set the default one:
     *  DELETE /api/settings
     *    {
     *        "email": string
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {}
     *    }
     * 
     * @return mixed any value that will encoded into JSON "result" field.
     * @throws UnsupportedMethodException current REST method not supported.
     */
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
                if (array_key_exists("job", $this->data)) {
                    $options["job"] = $this->data["job"];
                }
                if (array_key_exists("role", $this->data)) {
                    $options["role"] = $this->data["role"];
                }
                if (array_key_exists("company", $this->data)) {
                    $options["company"] = $this->data["company"];
                }
                $this->db->setInfoSettings($this->data["email"], $options);
                $this->checkErrorThrowException();
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
                $this->checkErrorThrowException();
                $result = null;
                break;
            default:
                throw new UnsupportedMethodException();
        }
        return $result;
    }

    /**
     * Handle the /api/noncompliances REST endpoint.
     * 
     * Get non compliances list ordered by most recent.
     * You can provide an optional word to search for in the noncompliance comment:
     *  GET /api/noncompliances
     *    {
     *        "resultsPerPage": int,
     *        "pageNumber": int,
     *        "search": string | undefined
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": [
     *                      {
     *                          "nonComplianceCode": int
     *                      }
     *                  ] | {}
     *    }
     * 
     * Return the total of all noncompliances separated by their status and
     * statistics about the noncompliances in the last 30 days:
     *  POST /api/noncompliances
     *    {}
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {
     *                      "totalNonCompliances": {
     *                          "new": int,
     *                          "progress": int,
     *                          "review": int,
     *                          "closed": int
     *                      },
     *                      "days": [
     *                                  {
     *                                      "date": string (YYYY-MM-DD),
     *                                      "new": int,
     *                                      "progress": int,
     *                                      "review": int,
     *                                      "closed": int
     *                                  }  
     *                              ]
     *                  } | {}
     *    }
     * 
     * Get available non compliance types:
     *  PUT /api/noncompliances
     *    {}
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": [
     *                      {
     *                          "code": int,
     *                          "name": string,
     *                          "description": string  
     *                      }
     *                  ] | {}
     *    }
     * 
     * @return mixed any value that will encoded into JSON "result" field.
     * @throws UnsupportedMethodException current REST method not supported.
     */
    protected function noncompliances(): mixed {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Get non compliances list
                $this->jsonKeysOK(array("resultsPerPage", "pageNumber"));
                $search = (array_key_exists("search", $this->data)) ? $this->data["search"] : "";
                $result = $this->db->getNonCompliances($this->data["resultsPerPage"], $this->data["pageNumber"], $search);
                $this->checkErrorThrowException();
                break;
            case HTTP_POST:
                // Get current non compliances stats
                $result = $this->db->getNonCompliancesStats();
                $this->checkErrorThrowException();
                break;
            case HTTP_PUT:
                // Get available non compliance types
                $result = $this->db->getPossibleNonCompliances();
                $this->checkErrorThrowException();
                break;
            default:
                throw new UnsupportedMethodException();
        }
        return $result;
    }

    /**
     * Handle the /api/noncompliance REST endpoint.
     * 
     * Get details about a noncompliance:
     *  GET /api/noncompliance
     *    {
     *        "nonCompliance": int,
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": [
     *                      {
     *                          "origin": "internal" | "customer" | "supplier",
     *                          "nonComplianceType": int,
     *                          "nonComplianceDate": string (YYYY-MM-DD),
     *                          "shippingLot": string,
     *                          "comment": string | undefined,
     *                          "managerEmail": string | undefined,
     *                          "analysisEndDate": string | undefined,
     *                          "checkEndDate": string | undefined,
     *                          "result": string | undefined, 
     *                      }
     *                  ] | {}
     *    }
     * 
     * Change noncompliance status (only to next step, if currently is check, the new value can be only result):
     *  POST /api/noncompliance
     *    {
     *       "nonCompliance": int,
     *       "status": "analysys" | "check" | "result"
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {}
     *    }
     * 
     * Create a new noncompliance:
     *  PUT /api/noncompliance
     *    {
     *        "nonComplianceOrigin": "internal" | "customer" | "supplier",
     *        "nonComplianceType": int,
     *        "shippingLot": string,
     *        "comment": string | undefined
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {}
     *    }
     * 
     * @return mixed any value that will encoded into JSON "result" field.
     * @throws UnsupportedMethodException current REST method not supported.
     */
    protected function noncompliance(): mixed {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Get details about a noncompliance
                $this->jsonKeysOK(array("nonCompliance"));
                $result = $this->db->getNonComplianceDetails($this->data["nonCompliance"]);
                $this->checkErrorThrowException();
                break;
            case HTTP_POST:
                // Change noncompliance status
                $this->jsonKeysOK(array("nonCompliance", "status"));
                $this->db->editNonCompliance($this->data["nonCompliance"], $this->data["status"]);
                $this->checkErrorThrowException();
                $result = null;
                break;
            case HTTP_PUT:
                // Create a new noncompliance
                $this->jsonKeysOK(array("nonComplianceOrigin", "nonComplianceType", "shippingLot"));
                $comment = array_key_exists("comment", $this->data) ? $this->data["comment"] : null;
                $this->db->addNoncompliance(
                    $this->data["nonComplianceOrigin"],
                    $this->data["nonComplianceType"],
                    $this->data["shippingLot"],
                    $comment
                );
                $this->checkErrorThrowException();
                $result = null;
                break;
            default:
                throw new UnsupportedMethodException();
        }
        return $result;
    }

    /**
     * Handle the /api/tickets REST endpoint.
     * 
     * Get all tickets by page:
     *  GET /api/tickets
     *    {
     *        "resultsPerPage": int,
     *        "pageNumber": int
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": [
     *                      {
     *                          "vatNum": string,
     *                          "nonComplianceCode": int
     *                      }
     *                  ] | {}
     *    }
     * 
     * Return the total of all tickets from the start and
     * statistics about the tickets in the last 30 days:
     *  POST /api/tickets
     *    {}
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {
     *                      "totalTickets": int,
     *                      "days": [
     *                                  {
     *                                      "date": string (YYYY-MM-DD),
     *                                      "counter": int
     *                                  }  
     *                              ]
     *                  } | {}
     *    }
     * 
     * @return mixed any value that will encoded into JSON "result" field.
     * @throws UnsupportedMethodException current REST method not supported.
     */
    protected function tickets(): mixed {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Get all tickets
                $this->jsonKeysOK(array("resultsPerPage", "pageNumber"));
                // Array of vatNum and nonComplianceCode
                $result = $this->db->getTickets($this->data["resultsPerPage"], $this->data["pageNumber"]);
                $this->checkErrorThrowException();
                break;
            case HTTP_POST:
                // Return statistics about the tickets in the last 30 days
                // totalTickets, days: array of date and counter
                $result = $this->db->getTicketStats();
                $this->checkErrorThrowException();
                break;
            default:
                throw new UnsupportedMethodException();
        }
        return $result;
    }

    /**
     * Handle the /api/ticket REST endpoint.
     * 
     * Get details about a single ticket:
     *  GET /api/ticket
     *    {
     *        "vat": string,
     *        "nonCompliance": int
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {
     *                      "customerCompanyName": string,
     *                      "customerCompanyAddress": string,
     *                      "shippingCode": string,
     *                      "productQuantity": int,
     *                      "problemDescription": string,
     *                      "ticketAnswer": string | undefined,
     *                      "status": "new" | "progress" | "closed"
     *                  } | {}
     *    }
     * 
     * Set an answer to a ticket:
     *  POST /api/ticket
     *    {
     *        "vat": string,
     *        "nonCompliance": int,
     *        "answer": string
     *    }
     *  Result:
     *    {
     *        "success": bool,
     *        "error": undefined | string,
     *        "result": {}
     *    }
     * 
     * Close a ticket:
     *  DELETE /api/ticket
     *    { 
     *        "vat": string,
     *        "nonCompliance": int
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
    protected function ticket(): mixed {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Get details about a single ticket
                $this->jsonKeysOK(array("vat", "nonCompliance"));
                // customerCompanyName, customerCompanyAddress, shippingCode, productQuantity, problemDescription, status
                // status can be new, progress or closed
                $result = $this->db->getTicketDetails($this->data["vat"], $this->data["nonCompliance"]);
                if ($result["answer"] === null) {
                    unset($result["answer"]);
                }
                $this->checkErrorThrowException();
                break;
            case HTTP_POST:
                // Set an answer to a ticket
                $this->jsonKeysOK(array("vat", "nonCompliance", "answer"));
                $this->db->answerToTicket($this->data["vat"], $this->data["nonCompliance"], $this->data["answer"]);
                $this->checkErrorThrowException();
                $result = null;
                break;
            case HTTP_DELETE:
                // Close a ticket
                $this->jsonKeysOK(array("vat", "nonCompliance"));
                $this->db->closeTicket($this->data["vat"], $this->data["nonCompliance"]);
                $this->checkErrorThrowException();
                $result = null;
                break;
            default:
                throw new UnsupportedMethodException();
        }
        return $result;
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
        } else {
            showError("Invalid endpoint", HTTP_BAD_REQUEST);
        }
    }

    /**
     * Clean current database connession.
     */
    public function close(): void {
        $this->db->close();
    }
}
