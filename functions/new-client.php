<?php
session_start();

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'],6,1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

if (empty($_GET['name'])) {
    die("Name not specified.");
}
if (empty($_GET['uuid'])) {
    die("UUID not specified.");
}
if (!preg_match('/[\w\s\.\-]+/', $_GET['name'])) {
    die("Malformed name");
}
if (strlen($_GET['uuid'])!==36||!preg_match('/[a-fA-F0-9\-]+/', $_GET['uuid'])) {
    die("Malformed UUID");
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

require_once("db.php");
$db=new Db;
$db->connect();

$db->execute("INSERT INTO clients(`name`,`UUID`) VALUES ('".$db->sanitize($_GET['name'])."', '".$db->sanitize($_GET['uuid'])."')");

$db->disconnect();

header("Location: ".$config->get('dir')."clients");
exit();
