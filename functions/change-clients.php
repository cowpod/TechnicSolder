<?php
session_start();
$config = require("./config.php");

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
$db->query("UPDATE `modpacks` SET `clients` = '".$db->sanitize($clients)."' WHERE `id`=".$_GET['id']
);
header("Location: ".$config['dir']."modpack?id=".$_GET['id']);
exit();
