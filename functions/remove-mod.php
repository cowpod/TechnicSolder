<?php

session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}

require_once('sanitize.php');

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->mods_delete()) {
    die('Insufficient permission!');
}

if (empty($_GET['id'])) {
    die("Mod not specified.");
}
if (empty($_GET['bid'])) {
    die("Build not specified.");
}

if (!is_numeric($_GET['id']) || !is_numeric($_GET['bid'])) {
    die('Malformed id/bid');
}

require_once("db.php");
$db = new Db();
$db->connect();

$modsq = $db->query("SELECT `mods` FROM `builds` WHERE `id` = ".$db->sanitize($_GET['bid']));
if (!$modsq) {
    die("Build id does not exist");
}

$mods = $modsq[0];
$modslist = explode(',', $mods['mods']);
$nmodlist = array_diff($modslist, [$_GET['id']]);
$modslist = implode(',', $nmodlist);
$db->execute("UPDATE `builds` SET `mods` = '".$modslist."' WHERE `id` = ".$db->sanitize($_GET['bid']));

$db->disconnect();

echo 'Mod removed';
exit();
