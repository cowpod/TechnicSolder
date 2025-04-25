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
    $config=new Config();
}

require_once("db.php");
$db=new Db;
$db->connect();

$db->execute("DELETE FROM `builds` WHERE `modpack` = '".$db->sanitize($_GET['id'])."'");
$db->execute("DELETE FROM `modpacks` WHERE `id` = '".$db->sanitize($_GET['id'])."'");

$db->disconnect();
header("Location: ".$config->get('dir')."dashboard");
exit();
