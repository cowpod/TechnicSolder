<?php
session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}
require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->mods_upload()) {
    die("Insufficient permission!");
}

if (empty($_GET['id'])) {
    die("Mod not specified.");
}
if (empty($_GET['bid'])) {
    die("Build not specified.");
}

require_once("db.php");
$db=new Db;
$db->connect();

$modsq = $db->query("SELECT `mods` FROM `builds` WHERE `id` = ".$_GET['bid']);
if (!$modsq) {
    die("Build id does not exist");
}

$mods = $modsq[0];
$db->execute("UPDATE `builds` SET `mods` = '".$mods['mods'].",".$_GET['id']."' WHERE `id` = ".$_GET['bid']);

echo 'Mod added';
exit();
