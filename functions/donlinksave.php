<?php
session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}

require_once('sanitize.php');

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->mods_edit()) {
    die('Insufficient permission!');
}
if (empty($_POST['id'])) {
    die("Mod not specified.");
}
if (empty($_POST['value'])) {
    die('Value (donation link) not specified.');
}
if (!is_numeric($_POST['id'])) {
    die('Malformed id');
}
if (!filter_var($_POST['value'], FILTER_VALIDATE_URL)){
    die("Malformed value (donation link)");
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$db->execute("UPDATE `mods` SET `donlink` = '{$_POST['value']}' WHERE `name` = '{$_POST['id']}'");

exit();
