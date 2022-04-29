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

    public function processRequest() {
    }

    public function close() {
    }
}
