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
    die("Insufficient permission!");
}
if (empty($_GET['confirm'])) {
    die("This is destructive, please confirm!");
}

echo "Preparing to delete all mods<br/>";
require_once("db.php");
$db = new Db();
$db->connect();

$ret = $db->execute("DELETE FROM mods WHERE type = 'mod'");
if (!$ret) {
    die("Could not delete from database.");
}
$db->disconnect();

$files = glob('../mods/*.zip');
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
        echo "deleting $file<br/>";
    }
}

die("All mods deleted.");
