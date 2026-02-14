<?php
header('Content-Type: application/json');
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->build_delete()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (empty($_GET['buildid'])) {
    die('{"status":"error","message":"Build id not specified."}');
}
if (empty($_GET['modpackid'])) {
    die('{"status":"error","message":"Modpack id not specified."}');
}

if (!is_numeric($_GET['buildid'])) {
    die('{"status":"error","message":"Malformed build id"}');
}
if (!is_numeric($_GET['modpackid'])) {
    die('{"status":"error","message":"Malformed modpack id"}');
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

// delete mods for build
if (!$db->execute("
    DELETE FROM build_mods 
    WHERE build_id = {$_GET['buildid']}
")) {
    die('{"status":"error","message":"Could not delete build mods"}');
}

// delete clients for build
if (!$db->execute("
    DELETE FROM build_clients 
    WHERE build_id = {$_GET['buildid']}
")) {
    die('{"status":"error","message":"Could not delete build mods"}');
}

// delete the build
if (!$db->execute("
    DELETE FROM builds 
    WHERE id = {$_GET['buildid']} 
        AND modpack = {$_GET['modpackid']}
")) {
    die('{"status":"error","message":"Could not delete build"}');
}

// set latest public build
if (!$db->execute("
    UPDATE modpacks 
    SET latest = (
        SELECT id
        FROM builds
        WHERE modpack = {$_GET['modpackid']}
            AND `public` = 1
        ORDER BY id DESC 
        LIMIT 1
    )
    WHERE id = {$_GET['modpackid']}
")) {
    die('{"status":"error","message":{"Could not set latest build"}');
}

// un-recommend modpack build (if recommended)
if (!$db->execute("
    UPDATE modpacks 
    SET recommended = null 
    WHERE id = {$_GET['modpackid']} 
        AND recommended = {$_GET['buildid']}
")) {
    die('{"status":"error","message":"Could not un-set recommended build for modpack"}');
}

// get latest and recommended builds for modpack
// can be null!

$response = ["latest" => null, "recommended" => null];

$getq = $db->query("
    SELECT b.id,b.name,b.minecraft
    FROM builds b
    JOIN modpacks m
        ON m.latest = b.id
    WHERE m.id = {$_GET['modpackid']}
        AND m.public = 1
        AND b.public = 1
");
if (!empty($getq)) {
    $response["latest"] = $getq[0];
}

$getq = $db->query("
    SELECT b.id,b.name,b.minecraft
    FROM builds b
    JOIN modpacks m
        ON m.recommended = b.id
    WHERE m.id = {$_GET['modpackid']}
        AND m.public = 1
        AND b.public = 1
");
if (!empty($getq)) {
    $response["recommended"] = $getq[0];
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

die('{"status":"succ","message":"Build deleted.","data":'.json_encode($response).'}');
