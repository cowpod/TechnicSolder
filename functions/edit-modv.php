<?php
session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}
require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->mods_edit()) {
    die('Insufficient permission!');
}
if (empty($_GET['id'])) {
    die("Mod not specified.");
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}
global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$result = $db->query("SELECT * FROM `mods` WHERE `id` = ".$_GET['id']);
if (!$result) {
    die("Mod id does not exist");
}

$mod = $result[0];
$url = isset($_POST['url']) ? $_POST['url'] : '';

$db->execute("UPDATE `mods`
    SET link='".    $db->sanitize($_POST['link'])."',"
    ."author ='".   $db->sanitize($_POST['author'])."',"
    ."donlink='".   $db->sanitize($_POST['donlink'])."',"
    ."version='".   $db->sanitize($_POST['version'])."',"
    ."mcversion='". $db->sanitize($_POST['mcversion'])."',"
    ."url='".       $db->sanitize($url)."',"
    ."md5='".       $db->sanitize($_POST['md5'])."',"
    ."loadertype='".$db->sanitize($_POST['loadertype'])."'"
    ."WHERE id=".     $db->sanitize($_GET['id'])
);

if ($_POST['submit']=="Save and close") {
    header("Location: ".$config->get('dir')."mod?id=".$mod['name']);
    exit();
}
header("Location: ".$config->get('dir')."modv?id=".$_GET['id']);
exit();
