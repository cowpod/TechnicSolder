<?php
session_start();
$config = require("./config.php");

if (empty($_GET['id'])) {
    die("Modpack not specified.");
}
if (empty($_GET['build'])) {
    die("Build not specified.");
}
if (empty($_GET['newname'])) {
    die("New name not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$sql = $db->query("SELECT `name` FROM `builds` WHERE `id` = " .$db->sanitize($_GET['build'])
);
if ($sql) {
    assert(sizeof($sql)==1);
    $name = $sql[0];
}

if (substr($_SESSION['perms'], 1, 1)!=="1") {
    echo '"{"status":"error","message":"Insufficient permission!"}';
    exit();
}
$db->execute("INSERT INTO builds(`name`,`minecraft`,`java`,`mods`,`modpack`)
            SELECT `name`,`minecraft`,`java`,`mods`,`modpack` FROM `builds` WHERE `id` = '".$_GET['build']."'"
);

$lbq = $db->query("SELECT `id` FROM `builds` ORDER BY `id` DESC LIMIT 1");
if ($lbq) {
    assert(sizeof($lbq)==1);
    $lb=$lbq[0];
}

$db->execute("UPDATE `builds` SET `name` = '".$db->sanitize($_GET['newname'])."' WHERE `id` = ".$lb['id']);
$db->execute("UPDATE `builds` SET `modpack` = '".$db->sanitize($_GET['id'])."' WHERE `id` = ".$lb['id']);

// get latest public build
$lpq = $db->query("SELECT id FROM builds WHERE public = 1 AND modpack = ".$db->sanitize($_GET['id'])." ORDER BY id DESC LIMIT 1");
if ($lpq && sizeof($lpq)==1) {
    $latest_public_build = $lpq[0];
    $db->execute("UPDATE modpacks SET latest = ".$latest_public_build['id']." WHERE id = ".$db->sanitize($_GET['id']));
}

header("Location: ".$config['dir']."modpack?id=".$_GET['id']);
exit();
