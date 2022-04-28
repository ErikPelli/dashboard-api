<?php
namespace Src;

class DatabaseHandler{
    private $db;

    public function __construct($db){
        $this->db = $db;
    }

    public function close(){
        $this->db->close();
    }
}