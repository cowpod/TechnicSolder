<?php
session_start();
$config = require("./config.php");

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
require("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$clients = implode(",", $_GET['client']);
$db->query("UPDATE `builds` SET `clients` = '".$db->sanitize($clients)."' WHERE `id`=".$_GET['id']
);
header("Location: ".$config['dir']."build?id=".$_GET['id']);
exit();
