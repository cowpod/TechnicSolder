<?php
session_start();
$config = require("./config.php");

if (empty($_POST['name'])) {
    die('{"status":"error","message":"Email not specified."}');
}
if (empty($_POST['display_name'])) {
    die('{"status":"error","message":"Name not specified."}');
}
if (empty($_POST['perms'])) {
    die('{"status":"error","message":"Perms not specified."}');
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die('{"status":"error","message":"Unauthorized request or login session has expired."}');
}
if ($_SESSION['privileged']) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$sql = $db->execute("UPDATE `users`
    SET `display_name` = '".$_POST['display_name']."', `perms` = '".$_POST['perms']."'
    WHERE `name` = '".$_POST['name']."'"
);

if ($sql) {
    echo '{"status":"succ","message":"Saved."}';
} else {
    echo '{"status":"error","message":"An error has occurred"}';
}
exit();