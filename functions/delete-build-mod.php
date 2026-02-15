<?php

session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}


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

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

if (!$db->execute("
    DELETE FROM build_mods 
    WHERE build_id = {$_GET['bid']}
    AND mod_id = {$_GET['id']}
")) {
    die("Could not remove mod from build");
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

echo 'Mod removed';
exit();
