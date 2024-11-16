<?php
session_start();
require_once('./config.php');
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
$db->execute("UPDATE `mods`
    SET `name` = '".$db->sanitize($_POST['name'])."',
    `pretty_name` = '".$db->sanitize($_POST['pretty_name'])."',
    `description` = '".$db->sanitize($_POST['description'])."'
    WHERE `name` = '".$db->sanitize($_GET['id'])."'");

if ($_POST['submit']=="Save and close") {
    header("Location: ".$config->get('dir')."lib-mods");
} else {
    header("Location: ".$config->get('dir')."mod?id=".$_POST['name']);
}
exit();