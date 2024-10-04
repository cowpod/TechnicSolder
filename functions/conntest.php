<?php
define('DBHOST', $_POST['db-host']);
define('DBUSER', $_POST['db-user']);
define('DBPASS', $_POST['db-pass']);
define('DBNAME', $_POST['db-name']);

try {
    $conn = mysqli_connect(DBHOST, DBUSER, DBPASS, DBNAME);
} catch (mysqli_sql_exception $e) {
    die('error'); // no details?
    error_log("Connection failed : ".$e);
}

// if (!$conn) {
//     die('error');
// } else {
    die('success'); //???
// }
