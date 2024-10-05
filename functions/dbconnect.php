<?php
$conf = require("config.php");
define('DBHOST', $conf['db-host']);
define('DBUSER', $conf['db-user']);
define('DBPASS', $conf['db-pass']);
define('DBNAME', $conf['db-name']);

try {
    $conn = mysqli_connect(DBHOST, DBUSER, DBPASS, DBNAME);;
} catch (mysqli_sql_exception $e) {
    die("Connection failed : " . $e);
    error_log("Connection failed : " . $e);
}

// if (!$conn) {
// 	die("Connection failed : " . mysqli_error($conn));
// }
