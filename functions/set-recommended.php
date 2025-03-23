<?php
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'],2,1)!=="1") {
    echo 'Insufficient permission!';
    exit();
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
