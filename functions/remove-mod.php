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
if (substr($_SESSION['perms'],1,1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

require_once("db.php");
$db=new Db;
$db->connect();

$modsq = $db->query("SELECT `mods` FROM `builds` WHERE `id` = ".$db->sanitize($_GET['bid']));
if ($modsq) {
    assert(sizeof($modsq)==1);
    $mods = $modsq[0];
}
$modslist = explode(',', $mods['mods']);
$nmodlist = array_diff($modslist, [$_GET['id']]);
$modslist = implode(',', $nmodlist);
$db->query("UPDATE `builds` SET `mods` = '".$modslist."' WHERE `id` = ".$db->sanitize($_GET['bid']));

$db->disconnect();
exit();
