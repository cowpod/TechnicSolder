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

// todo on fail, check if we're in a transaction, if we are, roll back!
// how should i return?

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
        if (empty($querystring)) {
            return false;
        }
        try {
            $stmt = $this->conn->query($querystring);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("db.php: query(): ".$e->getMessage());
            return false;
        }
    }

    public function execute(string $querystring): bool
    {
        if (empty($querystring)) {
            return false;
        }
        try {
            return $this->conn->exec($querystring);
        } catch (PDOException $e) {
            error_log("db.php: execute(): ".$e->getMessage());
            return false;
        }
    }

    public function beginTransaction(bool $tryAgain = false, int $tryMaxCount = 3) {
        $ret = false;
        try {
            if ($this->config->get('db-type')!='sqlite') {
                $this->conn->exec("SET autocommit=0");
            }
            
            $ret = $this->conn->beginTransaction();
        } catch (PDOException $e) {
            if ($this->config->get('db-type')!='sqlite') {
                $this->conn->exec("SET autocommit=1");
            }

            if ($tryAgain && $tryMaxCount > 0) {
                error_log("db.php: beginTransaction(): Trying again in 1s...");
                sleep(1);
                return $this->beginTransaction($tryAgain, $tryMaxCount - 1);
            } else {
                error_log("db.php: beginTransaction(): ".$e->getMessage());
                $this->rollBack();
                return false;
            }
        }
        return $ret;
    }

    public function commit() {
        $ret = false;
        try {
            $ret = $this->conn->commit();
            if ($this->config->get('db-type')!='sqlite') {
                $this->conn->exec("SET autocommit=1");
            }
        } catch (PDOException $e) {
            error_log("db.php: commit(): ".$e->getMessage());
            $this->rollBack();
            return false;
        }
        return $ret;
    }

    public function rollBack() {
        $ret = false;
        try {
            $ret = $this->conn->rollBack();
            if ($this->config->get('db-type')!='sqlite') {
                $this->conn->exec("SET autocommit=1");
            }
        } catch (PDOException $e) {
            error_log("db.php: rollBack(): ".$e->getMessage());
            return false;
        }
        return $ret;
    }
    public function quote(string $str): string {
        if (empty($str)) {
            return "''";
        }
        return $this->conn->quote($str);
    }
    /*
    todo: use prepared statements
    */
    public function sanitize(string $str): string
    {
        if (empty($str)) {
            return '';
        }
        $utf8_str = sanitize_string_utf8($str);
        $sql_str = str_replace(DB_SANITIZE_BACKLIST, '', $utf8_str);
        return $sql_str;
    }
    public function insert_id(): int
    {
        return $this->conn->lastInsertId();
    }

    public function error(): string
    {
        return $this->conn->errorInfo();
    }
}
