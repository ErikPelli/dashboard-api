<?php
namespace Src;

class RequestHandler{
    private $db;
    private $requestMethod;
    private $email;
    private $function;
    private $data;

    public function __construct($db, $requestMethod, $email, $function){
        $this->db = new DatabaseHandler($db);
        $this->requestMethod = $requestMethod;
        $this->email = $email;
        $this->function = $function;
        $this->data = parseJsonData();
    }

    private function parseJsonData() {
        return json_decode(file_get_contents('php://input'));
    }
}