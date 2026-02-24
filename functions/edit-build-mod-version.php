<?php

header('Content-Type: application/json');
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->build_edit()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (empty($_GET['id_new'])) {
    die('{"status":"error","message":"New mod not specified."}');
}
if (empty($_GET['id_old'])) {
    die('{"status":"error","message":"Old mod(s) not specified."}');
}
if (empty($_GET['bid'])) {
    die('{"status":"error","message":"Build not specified."}');
}

if (!is_numeric($_GET['bid']) || !is_numeric($_GET['id_new']) || !is_numeric($_GET['id_old'])) {
    die('{"status":"error","Malformed value(s)"}');
}

global $db;
require_once("db.php");
if (!isset($db)) {
    $db = new Db();
    $db->connect();
}

if (!$db->execute("
    UPDATE build_mods
    SET mod_id = {$_GET['id_new']}
    WHERE mod_id = {$_GET['id_old']}
    AND build_id = {$_GET['bid']}
")){
    die('{"status":"error","message":"Could update mod in build"}');
}

die('{"status":"succ","message":"Version changed sucessfully"');
