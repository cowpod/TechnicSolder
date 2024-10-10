<?php
class Db {
	private $conf=null;
	private $conn=null;

	private function get_including_file() {
	    $backtrace = debug_backtrace();
	    
	    // Index 0 is the current file, index 1 will be the file that included it
	    if (isset($backtrace[0]) && isset($backtrace[1])) {
	        return $backtrace[1]['file']."@".$backtrace[1]['line'];
	    } else {
	        return null;
	    }
	}

	function __construct() {
		if (file_exists("./config.php")) {
			$this->conf = include("./config.php");
		} elseif (file_exists("./functions/config.php")) {
			$this->conf = include("./functions/config.php");
		} else {
		    error_log("db.php: __construct(): Missing configuration!");
		}

		if (! (isset($this->conf['db-host']) 
			&& isset($this->conf['db-user'])
			&& isset($this->conf['db-pass'])
			&& isset($this->conf['db-name']))) {
		    error_log("db.php: __construct(): Configuration is missing some database information!");
		}
		
		// doesn't matter if we're missing config.php - assume we're setting up or testing
		return true;
	}

	public function test() {
		// POST
		$testconn;
		try {
		    $testconn = new PDO("mysql:host=".$_POST['db-host'].";dbname=".$_POST['db-name'].";charset=utf8", $_POST['db-user'], $_POST['db-pass']);
		    $testconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
		    error_log("db.php: test(): Connection test failed: ".$e->getMessage());
		    return false;
		}
		$testconn=null;
		return true; 
	}

	public function test2($host, $user, $pass, $name) {
		// arg
		$testconn;
		try {
		    $testconn = new PDO("mysql:host=$host;dbname=$name;charset=utf8", $user, $pass);
		    $testconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
		    error_log("db.php: test(): Connection test failed: ".$e->getMessage());
		    return false;
		}
		$testconn=null;
		return true; 
	}

	public function connect() {
		// config
		if (isset($this->conn)) {
			error_log("db.php: connect(): already connected!");
			return true;
		}
		try {
		    $this->conn = new PDO("mysql:host=".$this->conf['db-host'].";dbname=".$this->conf['db-name'].";charset=utf8", $this->conf['db-user'], $this->conf['db-pass']);
		} catch (PDOException $e) {
		    error_log("Connection failed : " . $e->getMessage());
		    return false;
		}
		return true;
	}

	public function connect2($host, $user, $pass, $name) {
		// arg
		if (isset($this->conn)) {
			error_log("db.php: connect2(): already connected!");
			return true;
		}
		try {
		    $this->conn = new PDO("mysql:host=$host;dbname=$name;charset=utf8", $user, $pass);
		} catch (PDOException $e) {
		    error_log("Connection failed : " . $e->getMessage());
		    return false;
		}
		return true;
	}

	public function disconnect(){
		$this->conn=null;
		return true;
	}

	public function query($querystring) {
		if (empty($querystring)) {
			return false;
		}
		$one_time_connection=false;

		// one-time connection if $conn isn't set
		if (!isset($this->conn)) {
			if ($this->connect()) {
				$one_time_connection=true;
			} else {
				error_log("db.php: query(): failed to open one-time connection");
				return false;
			}
		}
		// error_log('QUERY: '.$querystring);
		$ret=[];
		try {
			$stmt = $this->conn->prepare($querystring);
			$result = $stmt->execute();
			$stmt->setFetchMode(PDO::FETCH_ASSOC);
			foreach($stmt->fetchAll() as $row) {
			    array_push($ret, $row);
			}
		} catch (PDOException $e) {
		    error_log("db.php: query(): SQL query exception: ".$e->getMessage());
		}
		// error_log('RESULT: '.json_encode($ret));
		if ($one_time_connection) $this->disconnect();
		return $ret;
	}

	public function execute($querystring) {
		if (empty($querystring)) {
			return false;
		}
		$one_time_connection=false;
		// one-time connection if $conn isn't set
		if (!isset($this->conn)) {
			if ($this->connect()) {
				$one_time_connection=true;
			} else {
				error_log("db.php: execute(): failed to open one-time connection");
				return false;
			}
		}
		// error_log('QUERY: '.$querystring);
		try {
			$stmt = $this->conn->prepare($querystring);
			$result = $stmt->execute();
		} catch (PDOException $e) {
		    error_log("db.php: execute(): SQL query exception: ".$e->getMessage());
		}
		return $result;
	}


	public function sanitize($querystring) {
		return $querystring;
	}

	public function insert_id() {
		error_log("db.php: insert_id(): ".$this->conn->lastInsertId());
		return $this->conn->lastInsertId();
	}

	public function error() {
		error_log("db.php: error(): ".$this->conn->errorInfo());
		return $this->conn->errorInfo();
	}
}

?>