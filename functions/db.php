<?php

define('DB_SANITIZE_BACKLIST', [
    "'",    // single quote
    '"',    // double quote
    '\\',   // backslash
    ';',    // semicolon
    '--',   // SQL comment
    '#',    // MySQL comment
    '/*',   // Start of multiline comment
    '*/'    // End of multiline comment
]);
require_once('sanitize.php');

final class Db
{
    private $config = null;
    private $conn = null;

    public function __construct()
    {
        global $config;
        if (empty($config)) {
            if (file_exists("./configuration.php")) {
                require_once("./configuration.php");
                $this->config = new Config();
            } elseif (file_exists("./functions/configuration.php")) {
                require_once("./functions/configuration.php");
                $this->config = new Config();
            } elseif (file_exists("../functions/configuration.php")) {
                require_once("../functions/configuration.php");
                $this->config = new Config();
            } else {
                die('could not get configuration.php');
            }
        } else {
            $this->config = $config;
        }
        if ($this->config === null) {
            error_log("db.php: __construct(): Missing configuration.php?!");
        } elseif ($this->config->exists('db-type') && $this->config->get('db-type') == 'sqlite') {
            //
        } elseif (!$this->config->exists('db-host') || !$this->config->exists('db-user') || !$this->config->exists('db-name')) {
            error_log("db.php: __construct(): Configuration is missing some database information!");
        }
        return true; // can provide arguments later, bypassing config!
    }

    public function status()
    {
        return $this->conn !== null;
    }

    public function test2(string $dbtype, string $host, string $user, string $pass, string $name): bool // Arg
    {try {
        if ($dbtype == 'sqlite') {
            if (is_dir('./config')) {
                $testconn = new PDO('sqlite:./config/db.sqlite');
            } elseif (is_dir('../config')) {
                $testconn = new PDO('sqlite:../config/db.sqlite');
            } else {
                die('could not find config folder');
            }
        } else {
            $testconn = new PDO("$dbtype:host=$host;dbname=$name;charset=utf8", $user, $pass);
        }
        $testconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        error_log("db.php: test(): Connection test failed: ".$e->getMessage());
        return false;
    }
        return true;
    }

    public function connect(): bool // config
    {if ($this->config === null) { // __construct again, maybe we have configuration.php now
        $this->__construct();
    }
        if (!empty($this->conn)) {
            error_log("db.php: connect(): already connected!");
            return true;
        }
        try {
            if ($this->config->get('db-type') == 'sqlite') {
                if (is_dir('./config')) {
                    $this->conn  = new PDO('sqlite:./config/db.sqlite');
                } elseif (is_dir('../config')) {
                    $this->conn  = new PDO('sqlite:../config/db.sqlite');
                } else {
                    die('could not find config folder');
                }
            } else {
                $this->conn = new PDO($this->config->get('db-type').":host=".$this->config->get('db-host').";dbname=".$this->config->get('db-name').";charset=utf8", $this->config->get('db-user'), $this->config->get('db-pass'));
            }
        } catch (PDOException $e) {
            error_log("Connection failed : " . $e->getMessage());
            return false;
        }
        return true;
    }

    public function connect2(string $dbtype, string $host, string $user, string $pass, string $name): bool // Arg
    {if (!empty($this->conn)) {
        error_log("db.php: connect2(): already connected!");
        return true;
    }
        try {
            if ($dbtype == 'sqlite') {
                if (is_dir('./config')) {
                    $this->conn = new PDO('sqlite:./config/db.sqlite');
                } elseif (is_dir('../config')) {
                    $this->conn = new PDO('sqlite:../config/db.sqlite');
                } else {
                    die('could not find config folder');
                }
            } else {
                $this->conn = new PDO("$dbtype:host=$host;dbname=$name;charset=utf8", $user, $pass);
            }
        } catch (PDOException $e) {
            error_log("Connection failed : " . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @return true
     */
    public function disconnect(): bool
    {
        $this->conn = null;
        return true;
    }

    /**
     * @psalm-return false|list<mixed>
     */
    public function query(string $querystring): array|false
    {
        // error_log("db->query: '{$querystring}'");
        if (empty($querystring)) {
            return false;
        }
        $result_array = [];
        try {
            $stmt = $this->conn->prepare($querystring);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            foreach ($stmt->fetchAll() as $row) {
                array_push($result_array, $row);
            }
        } catch (PDOException $e) {
            error_log("db.php: query(): SQL query exception: ".$e->getMessage());
        }
        // error_log(json_encode($result_array, JSON_UNESCAPED_SLASHES));
        return $result_array;
    }

    public function execute(string $querystring): bool
    {
        // error_log("db->execute: '{$querystring}'");
        if (empty($querystring)) {
            return false;
        }
        try {
            $stmt = $this->conn->prepare($querystring);
            $result = $stmt->execute();
            return $result;
        } catch (PDOException $e) {
            error_log("db.php: execute(): SQL query exception: ".$e->getMessage());
            return false;
        }
    }

    public function sanitize(null|string $str): null|string
    {
        if (is_null($str)) {
            return $str;
        }
        $utf8_str = sanitize_string_utf8($str);
        $sql_str = str_replace(DB_SANITIZE_BACKLIST, '', $utf8_str);
        return $sql_str;
    }

    public function insert_id(): int
    {
        // error_log("db.php: insert_id(): ".$this->conn->lastInsertId());
        return $this->conn->lastInsertId();
    }

    public function error(): string
    {
        // error_log("db.php: error(): ".$this->conn->errorInfo());
        return $this->conn->errorInfo();
    }
}
