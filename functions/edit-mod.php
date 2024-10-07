<?php
session_start();
$config = require("./config.php");

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 4, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}
if (empty($_GET['id'])) {
    die("Mod not specified.");
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$result = $db->query("SELECT * FROM `mods` WHERE `id` = '".$db->sanitize($_GET['id'])."'");
if ($result) {
    assert(sizeof($result)==1);
    $mod = $result[0];
}
$db->query("UPDATE `mods`
    SET `name` = '".$db->sanitize($_POST['name'])."',
    `pretty_name` = '".$db->sanitize($_POST['pretty_name'])."',
    `description` = '".$db->sanitize($_POST['description'])."'
    WHERE `name` = '".$db->sanitize($_GET['id'])."'");

if ($_POST['submit']=="Save and close") {
    header("Location: ".$config['dir']."lib-mods");
    exit();
}
header("Location: ".$config['dir']."mod?id=".$_POST['name']);
exit();
