<?php
session_start();
require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 4, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}
if (empty($_POST['id'])) {
    die("Mod not specified.");
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$db->execute("UPDATE `mods` SET `donlink` = '".$db->sanitize($_POST['value'])."'
    WHERE `name` = '".$db->sanitize($_POST['id'])."'"
);
exit();
