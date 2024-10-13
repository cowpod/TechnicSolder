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

$result = $db->query("SELECT * FROM `mods` WHERE `id` = ".$_GET['id']);
if ($result) {
    assert(sizeof($result)==1);
    $mod = $result[0];
}
$db->execute("UPDATE `mods`
    SET link='".    $db->sanitize($_POST['link'])."',"
    ."author ='".   $db->sanitize($_POST['author'])."',"
    ."donlink='".   $db->sanitize($_POST['donlink'])."',"
    ."version='".   $db->sanitize($_POST['version'])."',"
    ."mcversion='". $db->sanitize($_POST['mcversion'])."',"
    ."url='".       $db->sanitize($_POST['url'])."',"
    ."md5='".       $db->sanitize($_POST['md5'])."',"
    ."loadertype='".$db->sanitize($_POST['loadertype'])."'"
    ."WHERE id=".     $db->sanitize($_GET['id'])
);

if ($_POST['submit']=="Save and close") {
    header("Location: ".$config['dir']."mod?id=".$mod['name']);
    exit();
}
header("Location: ".$config['dir']."modv?id=".$_GET['id']);
exit();
