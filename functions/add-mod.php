<?php
session_start();

if (empty($_GET['id'])) {
    die("Mod not specified.");
}
if (empty($_GET['bid'])) {
    die("Build not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 1, 1)!=="1") {
    die("Insufficient permission!");
}

require_once("db.php");
$db=new Db;
$db->connect();

$modsq = $db->query("SELECT `mods` FROM `builds` WHERE `id` = ".$_GET['bid']);
if ($modsq) {
    assert(sizeof($modsq)==1);
    $mods = $modsq[0];
}
$db->query("UPDATE `builds` SET `mods` = '".$mods['mods'].",".$_GET['id']."' WHERE `id` = ".$_GET['bid']);
exit();
