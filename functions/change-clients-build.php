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
if (substr($_SESSION['perms'], 1, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$clients = implode(",", $_GET['client']);
$db->execute("UPDATE `builds` SET `clients` = '".$db->sanitize($clients)."' WHERE `id`=".$_GET['id']);

header("Location: ".$config->get('dir')."build?id=".$_GET['id']);
exit();
