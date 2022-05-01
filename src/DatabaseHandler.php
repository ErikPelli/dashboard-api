<?php

namespace Src;

class DatabaseHandler {
    private \mysqli $db;

    public function __construct(\mysqli $db) {
        $this->db = $db;
    }

    public function error(): null|string {
        $err = $this->db->error;
        return ($err == "") ? null : $err;
    }

    public function close(): void {
        $this->db->close();
    }

    /********\
     * USER *
    \********/

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
        $password = password_hash($password, PASSWORD_DEFAULT);

        $this->db->begin_transaction();
        try {
            $this->db->query("INSERT INTO PersonalData(fiscalCode,firstName,lastName) VALUES('$fiscalCode','$firstName','$lastName')");
            $this->db->query("INSERT INTO Employee(fiscalCode,job,role,company,department) VALUES('$fiscalCode','{$_ENV['DEFAULT_JOB']}','{$_ENV['DEFAULT_ROLE']}','{$_ENV['DEFAULT_VAT']}',{$_ENV['DEFAULT_DEPARTMENT']})");
            $this->db->query("INSERT INTO User(fiscalCode,email,password) VALUES('$fiscalCode','$email','$password')");
            $this->db->commit();
        } catch (\mysqli_sql_exception $exception) {
            $this->db->rollback();
        }
    }

    public function userExists(string $email, string $password): bool {
        $email = $this->db->real_escape_string($email);
        $result = $this->db->query("SELECT password AS total FROM User WHERE email='$email'");
        if ($result === false || $result->num_rows != 1) {
            return false;
        } else {
            return password_verify($password, $result->fetch_column());
        }
    }

    public function getinfoUser(string $email): array {
        $email = $this->db->real_escape_string($email);
        $result = $this->db->query(
            "SELECT firstName, lastName, PersonalData.fiscalCode AS fiscalCode
            FROM PersonalData JOIN User ON PersonalData.fiscalCode = User.fiscalCode
            WHERE email = '$email'"
        );
        if ($result === false) {
            return array();
        } else if ($result->num_rows != 1) {
            throw new \LogicException("Invalid user database rows");
        } else {
            return $result->fetch_assoc();
        }
    }

    /************\
     * PASSWORD *
    \************/

    public function setPassword(string $email, string $password = null): void {
        $email = $this->db->real_escape_string($email);
        $password = ($password === null) ? "NULL" : "'" . password_hash($password, PASSWORD_DEFAULT) . "'";
        $this->db->query("UPDATE User SET password=$password WHERE email='$email' LIMIT 1");
        if ($this->db->affected_rows == 0) {
            throw new \LogicException("User doesn't exist");
        }
    }

    public function isPasswordSet(string $email): bool {
        $email = $this->db->real_escape_string($email);
        $result = $this->db->query("SELECT COUNT(*) AS total FROM User WHERE email='$email' AND password IS NOT NULL");
        return $result !== false && $result->fetch_column() == 1;
    }

    /************\
     * SETTINGS *
    \************/

    public function setInfoSettings(string $email, array $options): void {
        if (count($options) == 0) {
            // Nothing to do
            return;
        }

        // job, role, company fields
        $toSet = "";
        foreach ($options as $key => $value) {
            $key = $this->db->real_escape_string($key);
            $value = $this->db->real_escape_string($value);
            $toSet .= "{$key}='{$value}',";
        }
        // Remove last unusued comma
        $toSet = substr($toSet, 0, -1);

        $email = $this->db->real_escape_string($email);
        $this->db->query("UPDATE Employee SET {$toSet} WHERE fiscalCode=(SELECT fiscalCode FROM User WHERE email='{$email}') LIMIT 1");
        if ($this->db->affected_rows == 0) {
            throw new \LogicException("User doesn't exist");
        }
    }

    public function getInfoSettings(string $email): array {
        $email = $this->db->real_escape_string($email);
        $result = $this->db->query("SELECT job, role, company FROM Employee JOIN User WHERE email='{$email}'");
        if ($result === false) {
            return array();
        } else if ($result->num_rows != 1) {
            throw new \LogicException("Invalid user database rows");
        } else {
            return $result->fetch_assoc();
        }
    }

    /******************\
     * NONCOMPLIANCES *
    \******************/

    public function getNoncompliancesList(): array {
        //TODO
        return array();
    }

    /*****************\
     * NONCOMPLIANCE *
    \*****************/

    /***********\
     * TICKETS *
    \***********/

    /**********\
     * TICKET *
    \**********/

    public function getTicketDetails(string $vat, int $nonCompliance): array {
        $vat = $this->db->real_escape_string($vat);
        $nonCompliance = $this->db->real_escape_string($nonCompliance);
        $result = $this->db->query(
            "SELECT description, CO.name AS customerCompanyName, CO.address AS customerCompanyAddress,
            L.shippingCode AS shippingCode, L.quantity AS productQuantity, C.description AS problemDescription,
            CASE
                WHEN C.closed = 1 THEN \"closed\"
                WHEN C.answer IS NOT NULL THEN \"progress\"
                ELSE \"new\"
            END AS status
            FROM Complaint C
            JOIN Company CO ON C.vatNum=CO.vatNum
            JOIN Lot L ON C.shippingCode=L.shippingCode
            WHERE vatNum='$vat' AND nonComplianceCode='$nonCompliance'"
        );

        if ($result === false) {
            return array();
        } else if ($result->num_rows != 1) {
            throw new \LogicException("Invalid user database rows");
        } else {
            return $result->fetch_assoc();
        }
    }

    public function answerToTicket(string $vat, int $nonCompliance, string $answer): void {
        $vat = $this->db->real_escape_string($vat);
        $nonCompliance = $this->db->real_escape_string($nonCompliance);
        $answer = $this->db->real_escape_string($answer);
        $this->db->query("UPDATE Complaint SET answer='$answer' WHERE vatNum='$vat' AND nonComplianceCode='$nonCompliance'");
        if ($this->db->affected_rows == 0) {
            throw new \LogicException("Complaint not found");
        }
    }

    public function closeTicket(string $vat, int $nonCompliance): void {
        $vat = $this->db->real_escape_string($vat);
        $nonCompliance = $this->db->real_escape_string($nonCompliance);
        $this->db->query("UPDATE Complaint SET closed=1 WHERE vatNum='$vat' AND nonComplianceCode='$nonCompliance'");
        if ($this->db->affected_rows == 0) {
            throw new \LogicException("Complaint not found");
        }
    }
}
