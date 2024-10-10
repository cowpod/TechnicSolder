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
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 3, 1)!=="1") {
    die("Insufficient permission!");
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$db->execute("INSERT INTO `mods`
    (`name`, `pretty_name`, `md5`, `url`, `link`, `author`, `donlink`, `description`, `version`, `mcversion`, `type`) VALUES ( 
        '".$db->sanitize($_POST['name'])."',
        '".$db->sanitize($_POST['pretty_name'])."',
        '".$db->sanitize($_POST['md5'])."',
        '".$db->sanitize($_POST['url'])."',
        '".$db->sanitize($_POST['link'])."',
        '".$db->sanitize($_POST['author'])."',
        '".$db->sanitize($_POST['donlink'])."',
        '".$db->sanitize($_POST['dscription'])."',
        '".$db->sanitize($_POST['version'])."',
        '".$db->sanitize($_POST['mcversion'])."',
        'mod')");

header("Location: ../lib-mods");
exit();
