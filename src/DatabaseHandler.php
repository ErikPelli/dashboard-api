<?php

namespace Src;

class DatabaseHandler {
    private \mysqli $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function error() {
        $err = $this->db->error;
        if ($err == "") {
            $err = null;
        }
        return $err;
    }

    public function close() {
        $this->db->close();
    }
}
