<?php
session_start();
require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

if (empty($_GET['id'])) {
    die("Modpack not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 0, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

require_once("db.php");
$db=new Db;
$db->connect();

$clients = implode(",", $_GET['client']);
$db->execute("UPDATE `modpacks` SET `clients` = '".$db->sanitize($clients)."' WHERE `id`=".$_GET['id']);

header("Location: ".$config->get('dir')."modpack?id=".$_GET['id']);
exit();
