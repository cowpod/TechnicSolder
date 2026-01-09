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
$db = new Db();
$db->connect();

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

$setrecq = $db->execute("UPDATE modpacks SET recommended = {$_GET['buildid']} WHERE id = {$_GET['modpackid']}");
if (!$setrecq) {
    die('{"status":"error","message":"Could not set recommended build to '.$_GET['buildid'].' for modpack '.$_GET['modpackid'].'"}');
}

$bq = $db->query("SELECT * FROM builds WHERE id = {$_GET['buildid']}");
if (!$bq || empty($bq[0]['name']) || empty($bq[0]['minecraft'])) {
    die('{"status":"error","name": null, "mc": null}');
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

die('{"status":"succ","name": "'.$bq[0]['name'].'", "mc": "'.$bq[0]['minecraft'].'"}');
