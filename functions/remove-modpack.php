<?php
session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'],0,1)!=="1") {
    echo 'Insufficient permission!';
    exit();
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
