<?php
session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'],1,1)!=="1") {
    echo 'Insufficient permission!';
    exit();
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
