<?php

header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->build_publish()) {
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
if (!in_array($_GET['ispublic'], [0,1,'on','off'])) {
    die('{"status":"error","message":"Malformed ispublic"}');
}

if ($_GET['ispublic'] == 'on') {
    $_GET['ispublic'] = 1;
} elseif ($_GET['ispublic'] == 'off') {
    $_GET['ispublic'] = 0;
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

$hasminecraft = $db->query("SELECT 1 FROM builds WHERE minecraft IS NOT NULL AND id = {$_GET['buildid']}");
if (!$hasminecraft) {
    die('{"status":"error","message":"Build details are empty!"}');
}

if (!$db->execute("UPDATE builds SET public = {$_GET['ispublic']} WHERE id = {$_GET['buildid']}")){
    die('{"status":"error","message":"Could not set public"}');
}

if (!$db->execute("UPDATE modpacks SET latest = {$_GET['buildid']} WHERE id = {$_GET['modpackid']}")){
    die('{"status":"error","message":"Could not set latest"}');
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

die('{"status": "succ"}');
