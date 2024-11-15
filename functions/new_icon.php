<?php
session_start();
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die('{"status":"error","Unauthorized request or login session has expired."}');
}

$config = require("./config.php");
require_once("db.php");
$db=new Db;
$db->connect();

$icon = $_FILES["newIcon"]["tmp_name"];
if (filesize($icon)>15728640) {
    die('{"status":"error","message":"File size too large (max 15MB)"}');
}
$icon_base64 = base64_encode(file_get_contents($icon));

$query = $db->execute("UPDATE `users` SET `icon` = '".$icon_base64."' WHERE `name` = '".$_SESSION['user']."'");
if (!$query) {
    die('{"status":"error","Could not set user icon"}');
}

$db->disconnect();

die('{"status":"succ","message":"Succesfully set user icon"}');
exit();