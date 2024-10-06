<?php
session_start();

if (empty($_GET['id'])) {
    die("New mod not specified.");
}
if (empty($_GET['mod'])) {
    die("Old mod not specified.");
}
if (empty($_GET['bid'])) {
    die("Build not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 1, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

global $db;
require("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$modsq = $db->query("SELECT `mods` FROM `builds` WHERE `id` = ".$db->sanitize($_GET['bid'])
);
$mods = ($modsq);
$modslist = explode(',', $mods['mods']);
$nmodlist = array_diff($modslist, [$_GET['mod']]);
array_push($nmodlist, $_GET['id']);
$modslist = implode(',', $nmodlist);
$db->query("UPDATE `builds` SET `mods` = '".$modslist."' WHERE `id` = ".$db->sanitize($_GET['bid'])
);

exit();
