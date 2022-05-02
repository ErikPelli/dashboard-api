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

    public function getNonCompliances(int $resultsPerPage, int $page): array {
        if ($resultsPerPage <= 0 || $page <= 0) {
            throw new \LengthException("Invalid page visualization arguments");
        }
        $offset = $resultsPerPage * $page;

        $nonCompliances = $this->db->query(
            "SELECT code AS nonComplianceCode
            FROM NonCompliance
            ORDER BY date DESC
            LIMIT {$resultsPerPage} OFFSET {$offset}"
        );

        $result = array();
        if ($nonCompliances !== false) {
            while ($row = $nonCompliances->fetch_assoc()) {
                $tickets[] = $row;
            }
        }
        return $result;
    }

    public function getNonCompliancesStats(): array {
        $result = array();
        $this->db->begin_transaction();
        try {
            // Get all non compliances count divided by status
            $total = $this->db->query(
                "SELECT
                CASE
                    WHEN NCR.nonComplianceCode IS NOT NULL THEN \"closed\"
                    WHEN NCC.nonComplianceCode IS NOT NULL THEN \"review\"
                    WHEN NCA.nonComplianceCode IS NOT NULL THEN \"progress\"
                    ELSE \"new\"
                END AS status, COUNT(*) AS counter
                FROM NonCompliance NC
                LEFT JOIN NonComplianceAnalysis AS NCA ON NC.code = NCA.nonComplianceCode
                LEFT JOIN NonComplianceCheck AS NCC ON NC.code = NCC.nonComplianceCode
                LEFT JOIN NonComplianceResult AS NCR ON NC.code = NCR.nonComplianceCode
                GROUP BY status"
            );
            if ($total === false) {
                $this->db->rollback();
                return $result;
            }

            $result["totalNonCompliances"] = array(
                "new" => 0,
                "progress" => 0,
                "review" => 0,
                "closed" => 0
            );

            while ($row = $total->fetch_assoc()) {
                $result["totalNonCompliances"][$row["status"]] = $row["counter"];
            }

            // Get last 30 days non compliances (including today) ordered by most recent
            $nonCompliances = $this->db->query(
                "SELECT date,
                CASE
                    WHEN NCR.nonComplianceCode IS NOT NULL THEN \"closed\"
                    WHEN NCC.nonComplianceCode IS NOT NULL THEN \"review\"
                    WHEN NCA.nonComplianceCode IS NOT NULL THEN \"progress\"
                    ELSE \"new\"
                END AS status, COUNT(*) AS counter
                FROM NonCompliance NC
                LEFT JOIN NonComplianceAnalysis AS NCA ON NC.code = NCA.nonComplianceCode
                LEFT JOIN NonComplianceCheck AS NCC ON NC.code = NCC.nonComplianceCode
                LEFT JOIN NonComplianceResult AS NCR ON NC.code = NCR.nonComplianceCode
                WHERE date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE()
                GROUP BY date, status
                ORDER BY date DESC"
            );
            $this->db->commit();
        } catch (\mysqli_sql_exception $exception) {
            $this->db->rollback();
        }

        if ($nonCompliances !== false) {
            /*
                [{
                    "date"
                    "new"
                    "progress"
                    "review"
                    "closed"
                }]
            */
            $temp = array();
            while ($row = $nonCompliances->fetch_assoc()) {
                // Initialize for missing values
                if(!array_key_exists("new", $temp[$row["date"]])) {
                    $temp[$row["date"]]["new"] = 0;
                }
                if(!array_key_exists("progress", $temp[$row["date"]])) {
                    $temp[$row["date"]]["progress"] = 0;
                }
                if(!array_key_exists("review", $temp[$row["date"]])) {
                    $temp[$row["date"]]["review"] = 0;
                }
                if(!array_key_exists("closed", $temp[$row["date"]])) {
                    $temp[$row["date"]]["closed"] = 0;
                }

                $temp[$row["date"]]["date"] = $row["date"];
                $temp[$row["date"]][$row["status"]] = $row["counter"];
            }
            $result["days"] = array_values($temp);
        }
        return $result;
    }

    public function getPossibleNonCompliances(): array {
        $nonCompliances = $this->db->query("SELECT code, name, description FROM NonComplianceList");
        $result = array();
        if ($nonCompliances !== false) {
            while ($row = $nonCompliances->fetch_assoc()) {
                $result[] = $row;
            }
        }
        return $result;
    }

    /*****************\
     * NONCOMPLIANCE *
    \*****************/

    public function addNoncompliance(string $origin, int $type, string $comment = null): void {
        $type = $this->db->real_escape_string($type);
        $comment = ($comment !== null) ? "'" . $this->db->real_escape_string($comment) . "'" : "NULL";
        switch($origin) {
            case "internal":
                $origin = 1;
                break;
            case "customer":
                $origin = 2;
                break;
            case "supplier":
                $origin = 3;
                break;
            default:
                throw new \LogicException("Invalid origin");
        }

        $this->db->query("INSERT INTO NonCompliance(processOrigin,type,date,comment) VALUES($origin,$type,CURDATE(),$comment)");
    }

    public function getNonComplianceDetails($code) : array {
        if ($code == null) {
            throw new \InvalidArgumentException("Some parameters are empty");
        }
        $code = $this->db->real_escape_string($code);
        $result = $this->db->query(
            "SELECT NC.processOrigin AS origin, NC.type AS nonComplianceType, NC.date AS nonComplianceDate, NC.comment AS comment,
            NCA.expirationDate AS analysisEndDate, NCC.expirationDate AS checkEndDate, NCR.result AS result
            FROM NonCompliance NC
            LEFT JOIN NonComplianceAnalysis AS NCA ON NC.code = NCA.nonComplianceCode
            LEFT JOIN NonComplianceCheck AS NCC ON NC.code = NCC.nonComplianceCode
            LEFT JOIN NonComplianceResult AS NCR ON NC.code = NCR.nonComplianceCode
            WHERE NC.code = '$code'"
        );

        if ($result === false) {
            return array();
        } else if ($result->num_rows != 1) {
            throw new \LogicException("Invalid user database rows");
        } else {
            $result = $result->fetch_assoc();
            switch($result["origin"]) {
                case 1:
                    $result["origin"] = "internal";
                    break;
                case 2:
                    $result["origin"] = "customer";
                    break;
                case 3:
                    $result["origin"] = "supplier";
                    break;
                default:
                    throw new \LogicException("Invalid origin");
            }

            // Remove optional unset values
            if($result["comment"] === null) {
                unset($result["comment"]);
            }
            if($result["analysisEndDate"] === null) {
                unset($result["analysisEndDate"]);
            }
            if($result["checkEndDate"] === null) {
                unset($result["checkEndDate"]);
            }
            if($result["result"] === null) {
                unset($result["result"]);
            }

            return $result;
        }
    }

    public function editNonCompliance(string $code, string $status): void {
        switch($status) {
            case "analysys":
                $query = "INSERT INTO NonComplianceAnalysis(nonComplianceCode,date) VALUES('$code',DATE_ADD(CURDATE(), INTERVAL 1 MONTH))";
                break;
            case "check":
                $query = "INSERT INTO NonComplianceCheck(nonComplianceCode,date) VALUES('$code',DATE_ADD(CURDATE(), INTERVAL 1 MONTH))";;
                break;
            case "result":
                $query = "INSERT INTO NonComplianceResult(nonComplianceCode,comment) VALUES('$code','Corrected')";
                break;
            default:
                throw new \LogicException("Invalid origin");
        }

        $code = $this->db->real_escape_string($code);
        $this->db->query($query);
    }

    /***********\
     * TICKETS *
    \***********/

    public function getTickets(int $resultsPerPage, int $page): array {
        if ($resultsPerPage <= 0 || $page <= 0) {
            throw new \LengthException("Invalid page visualization arguments");
        }
        $offset = $resultsPerPage * $page;

        $tickets = $this->db->query(
            "SELECT vatNum, Complaint.nonComplianceCode AS nonComplianceCode
            FROM Complaint
            JOIN NonCompliance ON Complaint.nonComplianceCode=NonCompliance.code
            ORDER BY date DESC
            LIMIT {$resultsPerPage} OFFSET {$offset}"
        );

        $result = array();
        if ($tickets !== false) {
            while ($row = $tickets->fetch_assoc()) {
                $tickets[] = $row;
            }
        }
        return $result;
    }

    public function getTicketStats(): array {
        $result = array();
        $this->db->begin_transaction();
        try {
            $total = $this->db->query("SELECT COUNT(*) FROM Complaint");
            if ($total === false) {
                $this->db->rollback();
                return $result;
            }
            $result["totalTickets"] = (int) $total->fetch_column();

            // Get last 30 days tickets (including today) ordered by most recent
            $tickets = $this->db->query(
                "SELECT date, COUNT(*) AS counter
                FROM Complaint
                JOIN NonCompliance ON Complaint.nonComplianceCode=NonCompliance.code
                WHERE date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE()
                GROUP BY date
                ORDER BY date DESC"
            );
            $this->db->commit();
        } catch (\mysqli_sql_exception $exception) {
            $this->db->rollback();
        }

        if ($tickets !== false) {
            while ($row = $tickets->fetch_assoc()) {
                $result["days"][] = $row;
            }
        }
        return $result;
    }

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
