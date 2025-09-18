<?php

session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}

require_once('sanitize.php');

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->privileged()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (empty($_POST['id'])) {
    die('{"status":"error","message":"id not specified."}');
}
if (!is_numeric($_POST['id'])) {
    die('{"status":"error","message":"Malformed id"}');
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}

require_once("db.php");
$db = new Db();
$db->connect();

$removeq = $db->execute("DELETE FROM `users` WHERE `id` = {$_POST['id']}");

$db->disconnect();

if ($removeq) {
    die('{"status":"succ","message":"User removed."}');
} else {
    die('{"status":"succ","message":"Could not remove user."}');
}
