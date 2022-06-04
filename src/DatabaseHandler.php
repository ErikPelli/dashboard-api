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

    private static function handleResult(mixed $mysql_result): array {
        if ($mysql_result === false) {
            return array();
        } else if ($mysql_result->num_rows == 0) {
            throw new \LogicException("User doesn't exist");
        } else if ($mysql_result->num_rows != 1) {
            throw new \LogicException("Invalid user database rows");
        } else {
            return $mysql_result->fetch_assoc();
        }
    }

    /**********\
     * DETAILS *
    \**********/

    public function getUsers(): array {
        $emails = $this->db->query(
            "SELECT email FROM User"
        );

        $result = array();
        if ($emails !== false) {
            while ($row = $emails->fetch_assoc()) {
                $result[] = $row;
            }
        }
        return $result;
    }

    public function getLots(bool $limit, int $resultsPerPage = 0, int $page = 0): array {
        if ($limit) {
            if ($resultsPerPage <= 0 || $page <= 0) {
                throw new \LengthException("Invalid page visualization arguments");
            }
            $offset = $resultsPerPage * ($page - 1);
            $limit = "LIMIT {$resultsPerPage} OFFSET {$offset}";
        } else {
            $limit = "";
        }

        $lots = $this->db->query(
            "SELECT shippingCode, deliveryDate
            FROM Lot
            ORDER BY deliveryDate DESC
            {$limit}"
        );

        $result = array();
        if ($lots !== false) {
            while ($row = $lots->fetch_assoc()) {
                $result[] = $row;
            }
        }
        return $result;
    }

    /********\
     * USER *
    \********/

    public function registerUser(string $fiscalCode, string $firstName, string $lastName, string $email, string $password): void {
        if (strlen($fiscalCode) != 16) {
            throw new \LengthException("Mismatched fiscal code length, it must be 16");
        }

        if (empty($firstName) || empty($lastName) || empty($email)) {
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
            throw new \LogicException("User already exists");
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
        return DatabaseHandler::handleResult($result);
    }

    /************\
     * PASSWORD *
    \************/

    public function setPassword(string $email, string $password = null): void {
        $email = $this->db->real_escape_string($email);
        $password = ($password === null) ? "NULL" : "'" . password_hash($password, PASSWORD_DEFAULT) . "'";
        $this->db->query("UPDATE User SET password=$password WHERE email='$email' LIMIT 1");
        if ($this->db->affected_rows == 0) {
            throw new \LogicException("No changes performed");
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
            throw new \LogicException("No changes performed");
        }
    }

    public function getInfoSettings(string $email): array {
        $email = $this->db->real_escape_string($email);
        $result = $this->db->query(
            "SELECT job, role, company
            FROM Employee
            JOIN User ON Employee.fiscalCode=User.fiscalCode
            WHERE email='{$email}'"
        );
        return DatabaseHandler::handleResult($result);
    }

    /******************\
     * NONCOMPLIANCES *
    \******************/

    public function getNonCompliances(int $resultsPerPage, int $page, string $search = ""): array {
        if ($resultsPerPage <= 0 || $page <= 0) {
            throw new \LengthException("Invalid page visualization arguments");
        }
        $offset = $resultsPerPage * ($page - 1);

        if ($search != "") {
            $userSearch = $this->db->real_escape_string($search);
            $search = "WHERE comment LIKE '%$userSearch%'";

            // id search
            if (preg_match("/^[ICS]-[0-9]+/", $userSearch)) {
                switch ($userSearch[0]) {
                    case "I":
                        $origin = 1;
                        break;
                    case "C":
                        $origin = 2;
                        break;
                    case "S":
                        $origin = 3;
                        break;
                }
                $id = substr($userSearch, 2);
                $search .= " OR (processOrigin='$origin' AND code='$id')";
            }
        }

        $nonCompliances = $this->db->query(
            "SELECT code AS nonComplianceCode
            FROM NonCompliance
            {$search}
            ORDER BY date DESC, code DESC
            LIMIT {$resultsPerPage} OFFSET {$offset}"
        );

        $result = array();
        if ($nonCompliances !== false) {
            while ($row = $nonCompliances->fetch_assoc()) {
                settype($row["nonComplianceCode"], "int");
                $result[] = $row;
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
                $result["totalNonCompliances"][$row["status"]] = (int) $row["counter"];
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
                WHERE date > (CURDATE() - INTERVAL 30 DAY) AND date <= CURDATE()
                GROUP BY date, status
                ORDER BY date DESC"
            );
            $this->db->commit();
        } catch (\mysqli_sql_exception $exception) {
            $this->db->rollback();
        }

        if ($nonCompliances !== false) {
            $temp = array();

            // Initialize last 30 days
            for ($i = 0; $i < 30; $i++) {
                $date = new \DateTime("-$i days");
                $date = $date->format("Y-m-d");
                $temp[$date] = array(
                    "date" => $date,
                    "new" => 0,
                    "progress" => 0,
                    "review" => 0,
                    "closed" => 0,
                );
            }

            // Fill counters from DB
            while ($row = $nonCompliances->fetch_assoc()) {
                $temp[$row["date"]][$row["status"]] = (int) $row["counter"];
            }

            // Remove keys from temp array, they were used to avoid duplicated dates
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

    public function addNoncompliance(string $origin, int $type, string $lot, string $comment = null): void {
        $type = $this->db->real_escape_string($type);
        $comment = ($comment !== null && $comment != "") ? "'{$this->db->real_escape_string($comment)}'" : "''";
        switch ($origin) {
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

        $this->db->query("INSERT INTO NonCompliance(processOrigin,type,date,comment,lot) VALUES($origin,$type,CURDATE(),$comment,$lot)");
    }

    public function getNonComplianceDetails($code): array {
        if ($code == null) {
            throw new \InvalidArgumentException("Some parameters are empty");
        }
        $code = $this->db->real_escape_string($code);
        $result = $this->db->query(
            "SELECT NC.processOrigin AS origin, NC.type AS nonComplianceType, NC.date AS nonComplianceDate, NC.lot AS shippingLot, NC.comment AS comment,
            Manager.email AS managerEmail, NCA.expirationDate AS analysisEndDate, NCC.expirationDate AS checkEndDate, NCR.result AS result
            FROM NonCompliance NC
            LEFT JOIN NonComplianceAnalysis AS NCA ON NC.code = NCA.nonComplianceCode
            LEFT JOIN User AS Manager ON NCA.manager = Manager.fiscalCode
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
            switch ($result["origin"]) {
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

            settype($result["nonComplianceType"], "int");

            // Remove optional unset values
            if ($result["comment"] === null) {
                unset($result["comment"]);
            }
            if ($result["analysisEndDate"] === null) {
                unset($result["analysisEndDate"]);
            }
            if ($result["checkEndDate"] === null) {
                unset($result["checkEndDate"]);
            }
            if ($result["result"] === null) {
                unset($result["result"]);
            }
            if ($result["managerEmail"] === null) {
                unset($result["managerEmail"]);
            }

            return $result;
        }
    }

    public function editNonCompliance(string $code, string $status, string $manager = "", string $result = null): void {
        $code = $this->db->real_escape_string($code);
        if (strlen($manager) != 16) {
            // Default manager
            $manager = "RSNSMN84H04D612M";
        }

        if ($result === null) {
            $result = "The customer received a new set of working pen drives";
        }

        switch ($status) {
            case "analysys":
                $query = "INSERT INTO NonComplianceAnalysis(nonComplianceCode,manager,employee,expirationDate) VALUES('$code','$manager','PCCPTR55H17D612D',DATE_ADD(CURDATE(), INTERVAL 1 MONTH))";
                break;
            case "check":
                $query = "INSERT INTO NonComplianceCheck(nonComplianceCode,manager,employee,expirationDate) VALUES('$code','$manager','PCCPTR55H17D612D',DATE_ADD(CURDATE(), INTERVAL 1 MONTH))";
                break;
            case "result":
                $query = "INSERT INTO NonComplianceResult(nonComplianceCode,result,comment) VALUES('$code','$result','Corrected')";
                break;
            default:
                throw new \LogicException("Invalid origin");
        }

        $this->db->query($query);
    }

    /***********\
     * TICKETS *
    \***********/

    public function getTickets(int $resultsPerPage, int $page): array {
        if ($resultsPerPage <= 0 || $page <= 0) {
            throw new \LengthException("Invalid page visualization arguments");
        }
        $offset = $resultsPerPage * ($page - 1);

        $tickets = $this->db->query(
            "SELECT vatNum, Complaint.nonComplianceCode AS nonComplianceCode
            FROM Complaint
            JOIN NonCompliance ON Complaint.nonComplianceCode=NonCompliance.code
            ORDER BY date, nonComplianceCode
            LIMIT {$resultsPerPage} OFFSET {$offset}"
        );

        $result = array();
        if ($tickets !== false) {
            while ($row = $tickets->fetch_assoc()) {
                $result[] = $row;
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
                WHERE date > (CURDATE() - INTERVAL 30 DAY) AND date <= CURDATE()
                GROUP BY date
                ORDER BY date DESC"
            );
            $this->db->commit();
        } catch (\mysqli_sql_exception $exception) {
            $this->db->rollback();
        }

        if ($tickets !== false) {
            $temp = array();

            // Initialize last 30 days
            for ($i = 0; $i < 30; $i++) {
                $date = new \DateTime("-$i days");
                $date = $date->format("Y-m-d");
                $temp[$date] = array(
                    "date" => $date,
                    "counter" => 0,
                );
            }

            while ($row = $tickets->fetch_assoc()) {
                $temp[$row["date"]]["counter"] = (int) $row["counter"];
            }

            $result["days"] = array_values($temp);
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
            "SELECT CO.name AS customerCompanyName, CO.address AS customerCompanyAddress, L.shippingCode AS shippingCode, 
            L.quantity AS productQuantity, C.description AS problemDescription, C.answer AS ticketAnswer,
            CASE
                WHEN C.closed = 1 THEN \"closed\"
                WHEN C.answer IS NOT NULL THEN \"progress\"
                ELSE \"new\"
            END AS status
            FROM Complaint C
            JOIN Company CO ON C.vatNum=CO.vatNum
            JOIN Lot L ON C.shippingCode=L.shippingCode
            WHERE C.vatNum='$vat' AND C.nonComplianceCode='$nonCompliance'"
        );
        return DatabaseHandler::handleResult($result);
    }

    public function answerToTicket(string $vat, int $nonCompliance, string $answer): void {
        $vat = $this->db->real_escape_string($vat);
        $nonCompliance = $this->db->real_escape_string($nonCompliance);
        $answer = $this->db->real_escape_string($answer);
        $this->db->query("UPDATE Complaint SET answer='$answer' WHERE vatNum='$vat' AND nonComplianceCode='$nonCompliance'");
        if ($this->db->affected_rows == 0) {
            throw new \LogicException("No changes performed");
        }
    }

    public function closeTicket(string $vat, int $nonCompliance): void {
        $vat = $this->db->real_escape_string($vat);
        $nonCompliance = $this->db->real_escape_string($nonCompliance);
        $this->db->query("UPDATE Complaint SET closed=1 WHERE vatNum='$vat' AND nonComplianceCode='$nonCompliance'");
        if ($this->db->affected_rows == 0) {
            throw new \LogicException("No changes performed");
        }
    }

    public function addTicket(string $vat, int $nonCompliance, string $lot, string $description = null): void {
        $vat = $this->db->real_escape_string($vat);
        $nonCompliance = $this->db->real_escape_string($nonCompliance);
        $lot = $this->db->real_escape_string($lot);
        $description = ($description != null && $description != "") ? "'{$this->db->real_escape_string($description)}'" : "''";

        $this->db->query("INSERT INTO Complaint(vatNum,shippingCode,nonComplianceCode,description) VALUES('$vat','$lot','$nonCompliance', $description)");
        if ($this->db->affected_rows == 0) {
            throw new \LogicException("No changes performed");
        }
    }
}
