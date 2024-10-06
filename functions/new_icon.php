<?php
session_start();
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired.");
}

$config = require("./config.php");
require("db.php");
$db=new Db;
$db->connect();

$icon = $_FILES["newIcon"]["tmp_name"];
$iconbase = base64_encode(file_get_contents($icon));

$query = $db->query("UPDATE `users` SET `icon` = '".$iconbase."' WHERE `name` = '".$_SESSION['user']."'");

// echo mysql i_error($conn);

$db->disconnect();
