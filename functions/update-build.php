<?php
session_start();

if (empty($_GET['id'])) {
    die("id (build id) not specified");
}
if (empty($_POST['versions'])) {
    die("versions not specified");
}
if (empty($_POST['forgec'])) {
    die("forgec not specified");
}
if (empty($_POST['java'])) {
    die("java not specified");
}
if (empty($_POST['memory'])) {
    // die("memory not specified");
    $_POST['memory']='2048'; 
}
if (empty($_POST['ispublic'])) {
    // die("ispublic not specified");
    $_POST['ispublic']='off';
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 1, 1)!=="1") {
    die('Insufficient permission!');
}


$config = require('config.php');

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$user = $db->query("SELECT * FROM `builds` WHERE `id` = ".$db->sanitize($_GET['id']));
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
        $db->execute("UPDATE `builds` SET `mods` = '".$db->sanitize($_POST['versions'])."' WHERE `id` = ".$db->sanitize($_GET['id']));
    } else {
        $modslist2 = $modslist;
        $modslist2[0] = $_POST['versions'];
        $db->execute("UPDATE `builds` SET `mods` = '".$db->sanitize(implode(',',$modslist2))."' WHERE `id` = ".$db->sanitize($_GET['id']));
    }
}


$minecraft = $db->query("SELECT * FROM `mods` WHERE `id` = ".$db->sanitize($_POST['versions']));
if ($minecraft) {
    assert(sizeof($minecraft)==1);
    $minecraft=$minecraft[0];
}

$ispublic = $_POST['ispublic']=="on" ? 1 : 0;

$publicq = $db->query("SELECT public FROM builds WHERE id = ".$db->sanitize($_GET['id']));
error_log('PUBLIC: '.json_encode($publicq));
if ($publicq && sizeof($publicq)==1 && array_key_exists('public', $publicq[0])) {
    if ($publicq[0]['public']!=$ispublic) {
        if (substr($_SESSION['perms'], 2, 1)!=="1") {
            die('Insufficient permission!');
        }
    }
}

// actually update build
$db->execute("UPDATE `builds` SET `minecraft` = '".$minecraft['mcversion']."', `java` = '".$db->sanitize($_POST['java'])."', `memory` = '".$db->sanitize($_POST['memory'])."', `public` = ".$ispublic.", `loadertype` = '".$minecraft['loadertype']."' WHERE `id` = ".$db->sanitize($_GET['id']));

// get latest public build
$lpq = $db->query("SELECT id FROM builds WHERE public = 1 AND modpack = ".$user['modpack']." ORDER BY id DESC LIMIT 1");
if ($lpq && sizeof($lpq)==1) {
    $latest_public_build = $lpq[0];
    $db->execute("UPDATE modpacks SET latest = ".$latest_public_build['id']." WHERE id = ".$user['modpack']);
}

header('Location: '.$config['dir'].'build?id='.$_GET['id']);
