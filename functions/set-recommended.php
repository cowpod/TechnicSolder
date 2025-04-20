<?php
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}
require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->build_publish()) {
    die('Insufficient permission!');
}

if (empty($_GET['buildid'])) {
    die("Build ID not specified.");
}
if (empty($_GET['modpackid'])) {
    die("Build ID not specified.");
}
if (!is_numeric($_GET['buildid'])) {
    die("Malformed build id");
}
if (!is_numeric($_GET['modpackid'])) {
    die("Malformed modpack id");
}


require_once("db.php");
$db=new Db;
$db->connect();

$setrecq = $db->execute("UPDATE `modpacks` SET `recommended` = {$_GET['buildid']} WHERE `id` = {$_GET['modpackid']}");
if (!$setrecq) {
    die("Could not set recommended build to {$_GET['buildid']} for modpack {$_GET['modpackid']}");
}

$bq = $db->query("SELECT * FROM `builds` WHERE `id` = {$_GET['buildid']}");
if (!$bq) {
    die("Build id does not exist");
}

$build = $bq[0];
$response = array(
    "name" => $build['name'],
    "mc" => $build['minecraft']
);

$db->disconnect();

echo(json_encode($response));
exit();
