<?php

session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('sanitize.php');

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->build_edit()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (empty($_POST['id'])) {
    die('{"status":"error","message":"id (build id) not specified"}');
}
if (empty($_POST['versions'])) {
    die('{"status":"error","message":"versions not specified"}');
}
if (empty($_POST['forgec'])) {
    die('{"status":"error","message":"forgec not specified"}');
}
if (empty($_POST['java'])) {
    die('{"status":"error","message":"java not specified"}');
}
if (empty($_POST['memory'])) {
    // die("memory not specified");
    $_POST['memory'] = '2048';
}

if (empty($_POST['ispublic']) || $_POST['ispublic'] != 'on') {
    $_POST['ispublic'] = 'off';
}

if (!is_numeric($_POST['id'])) {
    die('{"status":"error","message":"Malformed id"}');
}
if (!is_numeric($_POST['memory'])) {
    die('{"status":"error","message":"Malformed memory"}');
}
if (strpbrk($_POST['versions'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed versions"}');
}
if (strpbrk($_POST['forgec'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed forgec"}');
}
if (strpbrk($_POST['java'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed java"}');
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
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

$userq = $db->query("SELECT * FROM `builds` WHERE `id` = ".$db->sanitize($_POST['id']));
if (!$userq) {
    die('{"status":"error","message":"Could not get build for id"}');
}
$user = $userq[0];

$modslist = isset($user['mods']) ? explode(',', $user['mods']) : [];
if (sizeof($modslist) == 1 && $modslist[0] == "") {
    unset($modslist[0]);
}

if ($_POST['forgec'] !== "none" || empty($modslist)) {
    if ($_POST['forgec'] == "wipe" || empty($modslist)) {
        if (!$db->execute("UPDATE `builds` SET `mods` = '".$db->sanitize($_POST['versions'])."' WHERE `id` = ".$db->sanitize($_POST['id']))){
            die('{"status":"error","message":"Could not wipe mods"}');
        }
    } else {
        $modslist2 = $modslist;
        $modslist2[0] = $_POST['versions'];
        if (!$db->execute("UPDATE `builds` SET `mods` = '".$db->sanitize(implode(',', $modslist2))."' WHERE `id` = ".$db->sanitize($_POST['id']))){
            die('{"status":"error","message":"Could not set mods"}');
        }
    }
}

$minecraft = $db->query("SELECT * FROM `mods` WHERE `type` = 'forge'");
if (!$minecraft){
    die('{"status":"error","message":"Could not get minecraft mod"}');
}
$minecraft = $minecraft[0];

$ispublic = $_POST['ispublic'] == "on" ? 1 : 0;

$publicq = $db->query("SELECT public FROM builds WHERE id = ".$db->sanitize($_POST['id']));
if ($publicq && sizeof($publicq) == 1 && array_key_exists('public', $publicq[0])) {
    if ($publicq[0]['public'] != $ispublic) {
        if (!$perms->build_publish()) {
            die('{"status":"error","message":"Insufficient permission!"}');
        }
    }
}

// actually update build
if (!$db->execute("UPDATE `builds` SET `minecraft` = '".$minecraft['mcversion']."', `java` = '".$db->sanitize($_POST['java'])."', `memory` = '".$db->sanitize($_POST['memory'])."', `public` = ".$ispublic.", `loadertype` = '".$minecraft['loadertype']."' WHERE `id` = ".$db->sanitize($_POST['id']))){
        die('{"status":"error","message":"Could not update build"}');}

// set latest public build.
if ($ispublic) {
    if (!$db->execute("UPDATE modpacks SET latest = {$db->sanitize($_POST['id'])} WHERE id = {$user['modpack']}")){
        die('{"status":"error","message":"Could not set public"}');
    }
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

die('{"status":"succ","message":"Build details updated."}');
