<?php
session_start();
require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die('{"status":"error","message":"Unauthorized request or login session has expired."}');
}
if (!$_SESSION['privileged']) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (empty($_POST['id'])) {
    die('{"status":"error","message":"id not specified."}');
}
if (!is_numeric($_POST['id'])) {
    die('{"status":"error","message":"Malformed id"}');
}
require_once("db.php");
$db=new Db;
$db->connect();

$removeq = $db->execute("DELETE FROM `users` WHERE `id` = {$_POST['id']}");

$db->disconnect();

if ($removeq) {
    die('{"status":"succ","message":"User removed."}');
} else {
    die('{"status":"succ","message":"Could not remove user."}');
}
