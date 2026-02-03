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

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

$hasmodloaderq = $db->query("
    SELECT 1
    FROM build_mods bm
    JOIN mods m
        ON m.id = bm.mod_id
    WHERE bm.build_id = {$db->sanitize($_GET['bid'])}
    AND m.type = 'forge'
    LIMIT 1
");
if (!$hasmodloaderq){
    die('{"status":"error","message":"Build is uninitialized. You need to set the minecraft version and modloader."}');
}

if (!$db->execute("
    DELETE FROM build_mods 
    WHERE build_id = {$_GET['bid']}
    AND mod_id = {$_GET['id_old']}
")){
    die('{"status":"error","message":"Could not delete mod from build"}');
}
if (!$db->execute("
    INSERT INTO build_mods (build_id,mod_id)
    VALUES ({$_GET['bid']}, {$_GET['id_new']})
")){
    die('{"status":"error","message":"Could not insert mod into build"}');
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

die('{"status":"succ","message":"Version changed sucessfully"');
