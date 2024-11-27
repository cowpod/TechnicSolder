<?php
header('Content-Type: application/json');
session_start();

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}
if (substr($_SESSION['perms'], 2, 1)!=="1") {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (empty($_GET['buildid'])) {
    die('{"status":"error","message":"Build id not specified"}');
}
if (empty($_GET['modpackid'])) {
    die('{"status":"error","message":"Modpack id not specified"}');
}
if (empty($_GET['ispublic'])) {
    die('{"status":"error","message":"ispublic not specified"}');
}

if (!is_numeric($_GET['buildid'])) {
    die('{"status":"error","message":"Malformed build id"}');
}
if (!is_numeric($_GET['modpackid'])) {
    die('{"status":"error","message":"Malformed modpack id"}');
}
if (!is_numeric($_GET['ispublic'])) {
    die('{"status":"error","message":"Malformed ispublic"}');
}
if ($_GET['ispublic']!=0 && $_GET['ispublic']!=1) {
    die('{"status":"error","message":"Malformed ispublic (0/1)"}');
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$hasminecraft = $db->query("SELECT 1 FROM builds WHERE minecraft IS NOT NULL AND id = {$_GET['buildid']}");
if (!$hasminecraft) {
    die('{"status":"error","message":"Build details are empty!"}');
}

$db->execute("UPDATE builds SET public = {$_GET['ispublic']} WHERE id = {$_GET['buildid']}");

$latestq = $db->execute("
    UPDATE modpacks 
    SET latest = (
        SELECT id 
        FROM builds 
        WHERE public=1
        AND modpack = {$db->sanitize($_GET['modpackid'])}
        ORDER BY id DESC
        LIMIT 1
    )
    WHERE id = {$_GET['modpackid']}
");

$bq = $db->query("SELECT * FROM `builds` WHERE `id` = {$_GET['buildid']}");
if ($bq) {
    assert(sizeof($bq)==1);
    $build = $bq[0];
}

die('{"status": "succ", "latestname": "'.$build['name'].'", "latestmc": "'.$build['minecraft'].'"}');