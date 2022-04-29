<?php

namespace Src;

class DatabaseHandler {
    private \mysqli $db;

    public function __construct(\mysqli $db) {
        $this->db = $db;
    }

    public function error() : null|string {
        $err = $this->db->error;
        return ($err == "") ? null : $err;
    }

    public function infoUser(string $email) {
        $sql = "SELECT firstName, lastName
        FROM PersonalData JOIN Employee ON PersonalData.fiscalCode = Employee.fiscalCode WHERE email = $email";
        return $this->db->query($sql);
    }

    public function userExists(string $email, string $password) : bool {
        $email = $this->db->real_escape_string($email);
        $password = hash("sha256", $password);

        $this->db->begin_transaction();
        $result = $this->db->query("SELECT COUNT(*) AS total FROM User WHERE email='$email' AND password='$password'");
        return $result->fetch_column("total") == 1;
    }

    public function registerUser(string $fiscalCode, string $firstName, string $lastName, string $email, string $password) : void {
        if(strlen($fiscalCode) != 16) {
            throw new \LengthException("Mismatched fiscal code length, it must be 16");
        }

        if(empty($fiscalCode) || empty($email)) {
            throw new \InvalidArgumentException("Some parameters are empty");
        }
        
        // Personal data
        $fiscalCode = $this->db->real_escape_string($fiscalCode);
        $firstName = $this->db->real_escape_string($firstName);
        $lastName = $this->db->real_escape_string($lastName);

        // User data
        $email = $this->db->real_escape_string($email);
        $password = hash("sha256", $password);

        $this->db->begin_transaction();
        try {
            $this->db->query("INSERT INTO PersonalData(fiscalCode,firstName,lastName) VALUES('$fiscalCode','$firstName','$lastName')");
            $this->db->query("INSERT INTO Employee(fiscalCode,job,role,company,department) VALUES('$fiscalCode','Java developer','programmer','IT111111111',1)");
            $this->db->query("INSERT INTO User(fiscalCode,email,password) VALUES('$fiscalCode','$email','$password')");
            $this->db->commit();
        } catch (\mysqli_sql_exception $exception) {
            $this->db->rollback();
        }
    }

    public function close() : void {
        $this->db->close();
    }
}
