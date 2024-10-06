<?php
class Db {
	private $conf=null;
	private $conn=null;

	function __construct() {
		if (file_exists("./config.php")) {
			$this->conf = include("./config.php");
		} elseif (file_exists("./functions/config.php")) {
			$this->conf = include("./functions/config.php");
		} else {
		    error_log("db.php: __construct(): Missing configuration!");
		}

		if (! (isset($conf['db-host']) 
			&& isset($conf['db-user'])
			&& isset($conf['db-pass'])
			&& isset($conf['db-name']))) {
			// die("Configuration is missing database information!");
		    error_log("db.php: __construct(): Configuration is missing some database information!");
		}

		// doesn't matter if we're missing config.php - assume we're setting up or testing
		return true;
	}

	public function test() {
		// pure
		$testconn;
		try {
		    $testconn = mysqli_connect($_POST['db-host'], $_POST['db-user'], $_POST['db-pass'], $_POST['db-name']);
		} catch (mysqli_sql_exception $e) {
		    error_log("db.php: test(): Connection test failed: ".$e);
		    return false;
		}
		mysqli_disconnect($testconn);
		return true; 
	}

	public function test2($host, $user, $pass, $name) {
		// pure
		$testconn;
		try {
		    $testconn = mysqli_connect($host, $user, $pass, $name);
		} catch (mysqli_sql_exception $e) {
		    error_log("db.php: test2(): Connection test failed: ".$e);
		    return false;
		}
		mysqli_disconnect($testconn);
		return true; 
	}

	public function connect() {
		if (isset($this->conn)) {
			error_log("db.php: connect(): already connected!");
			return true;
		}
		try {
		    $this->conn = mysqli_connect($this->conf['db-host'], $this->conf['db-user'], $this->conf['db-pass'], $this->conf['db-name']);;
		} catch (mysqli_sql_exception $e) {
		    error_log("Connection failed : " . $e);
		    return false;
		}
		return true;
	}

	public function connect2($host, $user, $pass, $name) {
		if (isset($this->conn)) {
			error_log("db.php: connect2(): already connected!");
			return true;
		}
		try {
		    $this->conn = mysqli_connect($host, $user, $pass, $name);;
		} catch (mysqli_sql_exception $e) {
		    error_log("Connection failed : " . $e);
		    return false;
		}
		return true;
	}


	public function disconnect(){
		return mysqli_close($this->conn);
	}

	public function query($querystring) {
		$one_time_connection=false;

		// one-time connection if $conn isn't set
		if (!isset($this->conn)) {
			if ($this->connect()) {
				$one_time_connection=true;
			} else {
				error_log("db.php: query(): failed to open one-time connection");
				return [];
			}
		}

		$ret;
		$result;

		try {
			$result = mysqli_query($this->conn, $querystring);
		} catch (mysqli_sql_exception $e) {
			// die("SQL query exception: ".$e);
		    error_log("db.php: query(): SQL query exception: ".$e);
		    if ($one_time_connection) $this->disconnect();
		    return [];
		}

		if (!$result) {
			// die("SQL query error: ".mysqli_error($result));
		    error_log("db.php: query(): SQL query error: ".mysqli_error($result));
		    if ($one_time_connection) $this->disconnect();
		    return [];
		}

    	if (mysqli_num_rows($result) == 0) {
		    if ($one_time_connection) $this->disconnect();
    		return [];
    	}

    	while($row = mysqli_fetch_array($result)) {
    		array_push($ret, $row);
    	}

		if ($one_time_connection) $this->disconnect();
    	return $ret;
	}

	public function sanitize($querystring) {
		return mysqli_real_escape_string($this->conn, $querystring);
	}

	public function insert_id() {
		error_log("db.php: insert_id(): ".mysqli_insert_id($this->conn));
		return mysqli_insert_id($this->conn);
	}

	public function error() {
		error_log("db.php: error(): ".mysqli_error($this->conn));
		return mysqli_error($this->conn);
	}
}

?>