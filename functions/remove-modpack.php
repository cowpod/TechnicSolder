<?php

session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}

require_once('sanitize.php');

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->modpack_delete()) {
    die('Insufficient permission!');
}
if (empty($_GET['id'])) {
    die("Modpack not specified.");
}
if (!is_numeric($_GET['id'])) {
    die("Malformed id.");
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}

require_once("db.php");
$db = new Db();
$db->connect();

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

if (!$db->execute("DELETE FROM `builds` WHERE `modpack` = '".$db->sanitize($_GET['id'])."'")){
    die("Could not delete build(s)");
}
if (!$db->execute("DELETE FROM `modpacks` WHERE `id` = '".$db->sanitize($_GET['id'])."'")){
    die("Could not delete modpack");
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

header("Location: ".$config->get('dir')."dashboard");
exit();
