<?php

header('Content-Type: application/json');
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

if (empty($_GET['md5'])) {
    die('{"status":"error","message":"MD5 missing"}');
}

if (!ctype_alnum($_GET['md5']) || strlen($_GET['md5']) !== 32) {
    die('{"status":"error","message":"Bad MD5"}');
}

require_once("db.php");
$db = new Db();
$db->connect();

$md5q = $db->query("SELECT 1 FROM mods WHERE jar_md5='{$_GET['md5']}' LIMIT 1");
if ($md5q) {
    die('{"status":"info","message":"Mod already in database."}');
} else {
    die('{"status":"succ","message":"Mod not in database."}');
}
// returns succ on mod not existing.
// do NOT change unless you also change what depends on this.
