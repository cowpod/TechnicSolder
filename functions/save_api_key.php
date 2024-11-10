<?php
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user'])) {
    die('{"status":"error", "message":"Unauthorized request or login session has expired!"}');
}

if (empty($_POST['api_key'])) {
    die('{"status":"error", "message":"no api_key provided"}');
}

$api_key=$_POST['api_key'];
if (!ctype_alnum($api_key)) {
    die('{"status":"error", "message":"invalid api_key provided"}');
}

if (isset($_SESSION['api_key']) && $_SESSION['api_key']==$api_key) {
    die('{"status":"succ", "message":"api_key is the same"}');
}

// required for user-settings.php...
require_once("db.php");
$db=new Db;
$db->connect();
$config = require("config.php");

require_once("user-settings.php");

set_setting('api_key', $api_key);

$db->disconnect();

die('{"status":"succ", "message":"successfuly set api_key"}');