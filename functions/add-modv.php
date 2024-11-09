<?php
session_start();

if (empty($_POST['pretty_name'])) {
    die("Name not specified.");
}
if (empty($_POST['name'])) {
    die("Slug not specified.");
}
if (empty($_POST['version'])) {
    die("Version not specified.");
}
if (empty($_POST['url'])) {
    die("URL not specified.");
}
if (empty($_POST['md5'])) {
    die("Md5 not specified.");
}
if (empty($_POST['mcversion'])) {
    die("Minecraft version not specified.");
}
if (empty($_POST['loadertype'])) {
    die("Loader type not specified.");
}

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 3, 1)!=="1") {
    die("Insufficient permission!");
}
$config = require("./config.php");

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$link = isset($_POST['link']) ? $db->sanitize($_POST['link']) : '';
$auth = isset($_POST['author']) ? $db->sanitize($_POST['author']) : '';
$desc = isset($_POST['description']) ? $db->sanitize($_POST['description']) : '';
$donlink = isset($_POST['donlink']) ? $db->sanitize($_POST['donlink']) : '';

$db->execute("INSERT INTO `mods`
    (`name`, `pretty_name`, `md5`, `url`, `link`, `author`, `donlink`, `description`, `version`, `mcversion`, `type`, `loadertype`) VALUES ( 
        '".$db->sanitize($_POST['name'])."',
        '".$db->sanitize($_POST['pretty_name'])."',
        '".$db->sanitize($_POST['md5'])."',
        '".$db->sanitize($_POST['url'])."',
        '".$link."',
        '".$auth."',
        '".$donlink."',
        '".$desc."',
        '".$db->sanitize($_POST['version'])."',
        '".$db->sanitize($_POST['mcversion'])."',
        'mod',
        '".$db->sanitize($_POST['loadertype'])."',
        )");

header("Location: ".$config['dir']."lib-mods");
exit();
