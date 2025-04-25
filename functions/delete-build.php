<?php
header('Content-Type: application/json');
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('sanitize.php');

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
if (!isset($db)){
    $db=new Db;
    $db->connect();
}


// delete the build
$del = $db->execute("DELETE FROM builds WHERE id = {$_GET['buildid']} AND modpack = {$_GET['modpackid']}");
if (!$del) {
    die('{"status":"error","message":"Could not delete build"}');
}

// set latest public build
$set_latestx = $db->execute("
    UPDATE modpacks 
    SET latest = (
        SELECT id
        FROM builds
        WHERE modpack = {$_GET['modpackid']}
        AND public = 1
        ORDER BY id DESC 
        LIMIT 1
    )
    WHERE id = {$_GET['modpackid']}
");
if (!$set_latestx) {
    die('{"status":"error","message":{"Could not set latest build"}');
}

// un-set recommended if needed for modpack
$unset_recommendedx = $db->execute("
    UPDATE modpacks 
    SET recommended = null 
    WHERE id = {$_GET['modpackid']} 
    AND recommended = {$_GET['buildid']}
");
if (!$unset_recommendedx) {
    die('{"status":"error","message":"Could not un-set recommended build for modpack"}');
}

// get latest and recommended builds for modpack
// can be empty!

$response = ["latest"=>null, "recommended"=>null];

$getq = $db->query("
    SELECT id,name,minecraft AS mcversion
    FROM builds
    WHERE id = (
        SELECT latest
        FROM modpacks 
        WHERE id = {$_GET['modpackid']}
        AND public = 1
    )
");
if (!empty($getq)) {
    // allow null/empty values for name, mcversion
    $response["latest"] = $getq[0];
}
$getq = $db->query("
    SELECT id,name,minecraft AS mcversion
    FROM builds
    WHERE id = (
        SELECT recommended
        FROM modpacks 
        WHERE id = {$_GET['modpackid']}
        AND public = 1
    )
");
if (!empty($getq)) {
    // allow null/empty values for name, mcversion
    $response["recommended"] = $getq[0];
}

die('{"status":"succ","message":"Build deleted.","data":'.json_encode($response).'}');
