<?php

namespace Src;

use Exception;

class DatabaseHandler {
    private \mysqli $db;

    public function __construct(\mysqli $db) {
        $this->db = $db;
    }

    public function error(): null|string {
        $err = $this->db->error;
        return ($err == "") ? null : $err;
    }

    public function infoUser(string $email): array {
        $email = $this->db->real_escape_string($email);
        $result = $this->db->query(
            "SELECT firstName AS fn, lastName AS ln, PersonalData.fiscalCode AS fc
            FROM PersonalData JOIN User ON PersonalData.fiscalCode = User.fiscalCode
            WHERE email = '$email'"
        );

        if ($result === false) {
            return array();
        } else if($result->num_rows != 1) {
            throw new \LogicException("Invalid user database rows");
        } else {
            return $result->fetch_assoc();
        }
    }

    public function userExists(string $email, string $password): bool {
        $email = $this->db->real_escape_string($email);
        $password = hash("sha256", $password);
        $result = $this->db->query("SELECT COUNT(*) AS total FROM User WHERE email='$email' AND password='$password'");
        return $result !== false && $result->fetch_column() == 1;
    }

    public function registerUser(string $fiscalCode, string $firstName, string $lastName, string $email, string $password): void {
        if (strlen($fiscalCode) != 16) {
            throw new \LengthException("Mismatched fiscal code length, it must be 16");
        }

        if (empty($fiscalCode) || empty($email)) {
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

    public function setPassword(string $email, string $password = null): void {
        $email = $this->db->real_escape_string($email);
        if ($password == null) {
            $password = "NULL";
        } else {
            $password = "'" . hash("sha256", $password) . "'";
        }
        $this->db->begin_transaction();
        try {
            $this->db->query("UPDATE User SET password = $password WHERE email= '$email'");
            if ($this->db->affected_rows == 1) {
                $this->db->commit();
            } else {
                $this->db->rollback();
                throw new \UnexpectedValueException("Email not found or more rows affected");
            }
        } catch (\mysqli_sql_exception $exception) {
            $this->db->rollback();
        }
    }

    public function isPasswordSet(string $email): bool {
        $email = $this->db->real_escape_string($email);
        $result = $this->db->query("SELECT COUNT(*) AS total FROM User WHERE email='$email' AND password IS NULL");
        return $result !== false && $result->fetch_column() == 1;
    }

    public function getTicketDetails(string $vat, int $nonCompliance): array {
        $vat = $this->db->real_escape_string($vat);
        $nonCompliance = $this->db->real_escape_string($nonCompliance);
        $result = $this->db->query(
            "SELECT description, CO.name AS customerCompanyName, CO.address AS customerCompanyAddress,
            L.shippingCode AS shippingCode, L.quantity AS productQuantity, N.code AS nonComplianceCode
            FROM Complaint C
            JOIN Company CO ON C.vatNum=CO.vatNum
            JOIN Lot L ON C.shippingCode=L.shippingCode
            JOIN NonCompliance N ON C.nonComplianceCode=N.code
            WHERE vatNum='$vat' AND nonComplianceCode='$nonCompliance'");
        
        // calculate status (transaction)

        if ($result === false) {
            return array();
        } else if($result->num_rows != 1) {
            throw new \LogicException("Invalid user database rows");
        } else {
            return $result->fetch_assoc();
        }
    }

    public function answerToTicket(string $vat, int $nonCompliance, string $answer): void {
        $vat = $this->db->real_escape_string($vat);
        $nonCompliance = $this->db->real_escape_string($nonCompliance);
        $answer = $this->db->real_escape_string($answer);
        $this->db->begin_transaction();
        try {
            $this->db->query("UPDATE Complaint SET answer='$answer' WHERE vatNum='$vat' AND nonComplianceCode='$nonCompliance'");
            if ($this->db->affected_rows == 1) {
                $this->db->commit();
            } else {
                $this->db->rollback();
                throw new \UnexpectedValueException("Complaint not found or more rows affected");
            }
        } catch (\mysqli_sql_exception $exception) {
            $this->db->rollback();
        }
    }

    public function closeTicket(string $vat, int $nonCompliance): void {
        $vat = $this->db->real_escape_string($vat);
        $nonCompliance = $this->db->real_escape_string($nonCompliance);
        $this->db->begin_transaction();
        try {
            $this->db->query("UPDATE Complaint SET closed=1 WHERE vatNum='$vat' AND nonComplianceCode='$nonCompliance'");
            if ($this->db->affected_rows == 1) {
                $this->db->commit();
            } else {
                $this->db->rollback();
                throw new \UnexpectedValueException("Complaint not found or more rows affected");
            }
        } catch (\mysqli_sql_exception $exception) {
            $this->db->rollback();
        }
    }

    public function close(): void {
        $this->db->close();
    }
}
