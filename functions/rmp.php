<?php
session_start();
$config = require("./config.php");

if (empty($_GET['id'])) {
    die("Modpack not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'],0,1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

require("db.php");
$db=new Db;
$db->connect();

$db->query("DELETE FROM `builds` WHERE `modpack` = '".$db->sanitize($_GET['id'])."'");
$db->query("DELETE FROM `modpacks` WHERE `id` = '".$db->sanitize($_GET['id'])."'");

$db->disconnect();
header("Location: ".$config['dir']."dashboard");
exit();
