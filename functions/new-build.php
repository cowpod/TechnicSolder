<?php
session_start();
$config = require("./config.php");

if (empty($_GET['id'])) {
    die("Modpack not specified.");
}
if (empty($_GET['name'])) {
    die("Name not specified.");
}
if (empty($_GET['type'])) {
    die("type not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'],1,1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

require_once("db.php");
$db=new Db;
$db->connect();

if ($_GET['type']=="update") {
    $db->execute("INSERT INTO builds(`name`,`minecraft`,`java`,`mods`,`modpack`,`public`) SELECT `name`,`minecraft`,`java`,`mods`,`modpack`,`public` FROM `builds` WHERE `modpack` = '".$db->sanitize($_GET['id'])."' ORDER BY `id` DESC LIMIT 1");
    $db->execute("UPDATE `builds` SET `name` = '".$db->sanitize($_GET['name'])."' WHERE `modpack` = ".$db->sanitize($_GET['id'])." ORDER BY `id` DESC LIMIT 1");
    $db->execute("UPDATE `builds` SET `public` = 0 WHERE `modpack` = ".$db->sanitize($_GET['id'])." ORDER BY `id` DESC LIMIT 1");
} else {
    $db->execute("INSERT INTO builds(`name`,`modpack`,`public`) VALUES ('".$db->sanitize($_GET['name'])."','".$db->sanitize($_GET['id'])."',0)");
}

$lpq = $db->query("SELECT `name`,`modpack`,`public` FROM `builds` WHERE `public` = 1 AND `modpack` = ".$db->sanitize($_GET['id'])." ORDER BY `id` DESC LIMIT 1");
if ($lpq && sizeof($lpq)==1) {
    $latest_public = $lpq[0];
} else {
    $db->disconnect();
    error_log("new-build.php: couldn't select latest build");
    die("new-build.php: couldn't select latest build");
}

if (!empty($latest_public['name'])) {
    $db->execute("UPDATE `modpacks` SET `latest` = '".$latest_public['name']."' WHERE `id` = ".$db->sanitize($_GET['id']));
}

$db->disconnect();
header("Location: ".$config['dir']."modpack?id=".$_GET['id']);
exit();
