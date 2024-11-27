<?php
header('Content-Type: application/json');
session_start();

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 1, 1)!=="1") {
    die("Insufficient permission!");
}

if (empty($_GET['buildid'])) {
    die("Build id not specified.");
}
if (empty($_GET['modpackid'])) {
    die("Modpack id not specified.");
}

if (!is_numeric($_GET['buildid'])) {
    die("Malformed build id");
}
if (!is_numeric($_GET['modpackid'])) {
    die("Malformed modpack id");
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$db->execute("DELETE FROM `builds` WHERE `id` = {$_GET['buildid']}");


$bq = $db->query("SELECT * FROM `builds` WHERE `modpack` = {$_GET['modpackid']} AND `public` = 1 ORDER BY `id` DESC LIMIT 1");
if ($bq) {
    assert(sizeof($bq)==1);
    $build = $bq[0];
    $response = array(
        "exists" => true,
        "name" => $build['name'],
        "mc" => $build['minecraft']
    );
} else {
    $response = array(
        "exists" => false
    );
}

$latestq = $db->execute("
    UPDATE modpacks 
    SET latest = (
        SELECT id 
        FROM builds 
        WHERE public=1
        AND modpack = {$_GET['modpackid']}
        ORDER BY id DESC
        LIMIT 1
    )
    WHERE id = {$_GET['modpackid']}
");
if (!$latestq) {
    die("Could not set latest build");
}

echo json_encode($response);
exit();
