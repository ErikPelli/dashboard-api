<?php

namespace Src;

class DatabaseHandler {
    private \mysqli $db;

    public function __construct(\mysqli $db) {
        $this->db = $db;
    }

    public function error() : null|string {
        $err = $this->db->error;
        if ($err == "") {
            $err = null;
        }
        return $err;
    }

    public function infoUser(string $email) {
        $sql = "SELECT firstName, lastName FROM PersonalData JOIN Employee ON PersonalData.fiscalCode = Employee.fiscalCode WHERE email = $email";
        return $this->db->query($sql);
    }

    public function registerUser($fiscalCode, $firstName, $lastName, $email, $username, $password) {
        $sql = "INSERT INTO";    //finire la query
    }

    public function close() : void {
        $this->db->close();
    }
}
