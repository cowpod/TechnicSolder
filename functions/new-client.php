<?php
session_start();
$config = require("./config.php");

require_once("db.php");
$db=new Db;
$db->connect();

if (empty($_GET['name'])) {
    die("Name not specified.");
}
if (empty($_GET['uuid'])) {
    die("UUID not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'],6,1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

$db->query("INSERT INTO clients(`name`,`UUID`) VALUES ('".$db->sanitize($_GET['name'])."', '".$db->sanitize($_GET['uuid'])."')");

$db->disconnect();

header("Location: ".$config['dir']."clients");
exit();
