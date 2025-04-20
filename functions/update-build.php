<?php
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
    $_POST['memory']='2048'; 
}
if (empty($_POST['ispublic'])) {
    // die("ispublic not specified");
    $_POST['ispublic']='off';
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$user = $db->query("SELECT * FROM `builds` WHERE `id` = ".$db->sanitize($_POST['id']));
if ($user) {
    assert(sizeof($user)==1);
    $user = $user[0];
}
$modslist = isset($user['mods']) ? explode(',', $user['mods']) : [];
if (sizeof($modslist)==1 && $modslist[0]==""){
    unset($modslist[0]);
}

// todo: rewrite this. no need to write to builds twice!
if ($_POST['forgec']!=="none"||empty($modslist)) {
    if ($_POST['forgec']=="wipe"||empty($modslist)) {
        $db->execute("UPDATE `builds` SET `mods` = '".$db->sanitize($_POST['versions'])."' WHERE `id` = ".$db->sanitize($_POST['id']));
    } else {
        $modslist2 = $modslist;
        $modslist2[0] = $_POST['versions'];
        $db->execute("UPDATE `builds` SET `mods` = '".$db->sanitize(implode(',',$modslist2))."' WHERE `id` = ".$db->sanitize($_POST['id']));
    }
}


$minecraft = $db->query("SELECT * FROM `mods` WHERE `id` = ".$db->sanitize($_POST['versions']));
if ($minecraft) {
    assert(sizeof($minecraft)==1);
    $minecraft=$minecraft[0];
}

$ispublic = $_POST['ispublic']=="on" ? 1 : 0;

$publicq = $db->query("SELECT public FROM builds WHERE id = ".$db->sanitize($_POST['id']));
error_log('PUBLIC: '.json_encode($publicq));
if ($publicq && sizeof($publicq)==1 && array_key_exists('public', $publicq[0])) {
    if ($publicq[0]['public']!=$ispublic) {
        if (!$perms->build_publish()) {
            die('{"status":"error","message":"Insufficient permission!"}');
        }
    }
}

// actually update build
$db->execute("UPDATE `builds` SET `minecraft` = '".$minecraft['mcversion']."', `java` = '".$db->sanitize($_POST['java'])."', `memory` = '".$db->sanitize($_POST['memory'])."', `public` = ".$ispublic.", `loadertype` = '".$minecraft['loadertype']."' WHERE `id` = ".$db->sanitize($_POST['id']));

// set latest public build.
if ($ispublic) {
    $db->execute("UPDATE modpacks SET latest = {$db->sanitize($_POST['id'])} WHERE id = {$user['modpack']}");
}

die('{"status":"succ","message":"Build details updated."}');
