<?php
class Db {
	private $config=null;
	private $conn=null;

	private function get_including_file() {
	    $backtrace = debug_backtrace();
	    if (isset($backtrace[0]) && isset($backtrace[1])) {
	        return $backtrace[1]['file']."@".$backtrace[1]['line'];
	    } else {
	        return null;
	    }
	}

	function __construct() {
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
			}
		} else {
			$this->config=$config;
		}
		if ($this->config===NULL) {
		    error_log("db.php: __construct(): Missing configuration.php?!");
		} elseif ($this->config->exists('db-type') && $this->config->get('db-type')=='sqlite') {
			// 
		} elseif($this->config->exists('db-host') && $this->config->exists('db-user') && $this->config->exists('db-pass') && $this->config->exists('db-name')) {
		    error_log("db.php: __construct(): Configuration is missing some database information!");
		}
		return true; // can provide arguments later, bypassing config!
	}

	public function test() { // POST
		if ($this->config===null) {  // __construct again, maybe we have configuration.php now
			$this->__construct();
		}
		try {
			if ($_POST['db-type']=='sqlite') {
				if (is_dir('./functions')) {
					$testconn = new PDO('sqlite:db.sqlite');
				} else {
					$testconn = new PDO('sqlite:../db.sqlite');
				}
			} else {
			    $testconn = new PDO($_POST['db-type'].":host=".$_POST['db-host'].";dbname=".$_POST['db-name'].";charset=utf8", $_POST['db-user'], $_POST['db-pass']);
			}
		    $testconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
		    error_log("db.php: test(): Connection test failed: ".$e->getMessage());
		    return false;
		}
		$testconn=null;
		return true; 
	}

	public function test2($dbtype, $host, $user, $pass, $name) { // Arg
		try {
			if ($dbtype=='sqlite') {
				if (is_dir('./functions')) {
					$testconn = new PDO('sqlite:db.sqlite');
				} else {
					$testconn = new PDO('sqlite:../db.sqlite');
				}
			} else {
		    	$testconn = new PDO("$dbtype:host=$host;dbname=$name;charset=utf8", $user, $pass);
		    }
		    $testconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
		    error_log("db.php: test(): Connection test failed: ".$e->getMessage());
		    return false;
		}
		$testconn=null;
		return true; 
	}

	public function connect() { // config
		if ($this->config===null) { // __construct again, maybe we have configuration.php now
			$this->__construct();
		}
		if (!empty($this->conn)) {
			error_log("db.php: connect(): already connected!");
			return TRUE;
		}
		try {
			if ($this->config->get('db-type')=='sqlite') {
				if (is_dir('./functions')) {
					$this->conn = new PDO('sqlite:db.sqlite');
				} else {
					$this->conn = new PDO('sqlite:../db.sqlite');
				}
			} else {
		    	$this->conn = new PDO($this->config->get('db-type').":host=".$this->config->get('db-host').";dbname=".$this->config->get('db-name').";charset=utf8", $this->config->get('db-user'), $this->config->get('db-pass'));
		    }
		} catch (PDOException $e) {
		    error_log("Connection failed : " . $e->getMessage());
		    return FALSE;
		}
		return TRUE;
	}

	public function connect2($dbtype, $host, $user, $pass, $name) { // Arg
		if (!empty($this->conn)) {
			error_log("db.php: connect2(): already connected!");
			return TRUE;
		}
		try {
			if ($dbtype=='sqlite') {
				if (is_dir('./functions')) {
					$this->conn = new PDO('sqlite:db.sqlite');
				} else {
					$this->conn = new PDO('sqlite:../db.sqlite');
				}
			} else {
		    	$this->conn = new PDO("$dbtype:host=$host;dbname=$name;charset=utf8", $user, $pass);
		    }
		} catch (PDOException $e) {
		    error_log("Connection failed : " . $e->getMessage());
		    return FALSE;
		}
		return TRUE;
	}

	public function disconnect(){
		$this->conn=null;
		return true;
	}

	public function query($querystring) {
		if (empty($querystring)) {
			return false;
		}
		$result_array=[];
		try {
			$stmt = $this->conn->prepare($querystring);
			$result = $stmt->execute();
			$stmt->setFetchMode(PDO::FETCH_ASSOC);
			foreach($stmt->fetchAll() as $row) {
			    array_push($result_array, $row);
			}
		} catch (PDOException $e) {
		    error_log("db.php: query(): SQL query exception: ".$e->getMessage());
		}
		return $result_array;
	}

	public function execute($querystring) {
		if (empty($querystring)) {
			return false;
		}
		try {
			$stmt = $this->conn->prepare($querystring);
			$result = $stmt->execute();
			return $result;
		} catch (PDOException $e) {
		    error_log("db.php: execute(): SQL query exception: ".$e->getMessage());
		}
	}


	public function sanitize($str) {
		if (empty($str)) {
			return $str; // allow either NULL or "" values
		} else {
			return addslashes(htmlspecialchars($str, ENT_QUOTES, 'UTF-8'));
		}
	}

	public function insert_id() {
		// error_log("db.php: insert_id(): ".$this->conn->lastInsertId());
		return $this->conn->lastInsertId();
	}

	public function error() {
		// error_log("db.php: error(): ".$this->conn->errorInfo());
		return $this->conn->errorInfo();
	}
}

?>