<?php
header('Content-Type: application/json');
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}
require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->build_create()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (empty($_POST['dest_modpack_id'])) {
    die('{"status":"error","message":"Modpack ID not specified."}');
}
if (empty($_POST['src_build_id'])) {
    die('{"status":"error","message":"Build ID not specified."}');
}
if (empty($_POST['new_build_name'])) {
    die('{"status":"error","message":"New name not specified."}');
}

if (!is_numeric($_POST['dest_modpack_id'])) {
    die('{"status":"error","message":"Malformed id"}');
}
if (!is_numeric($_POST['src_build_id'])) {
    die('{"status":"error","message":"Malformed build"}');
}
if (!preg_match('/^[a-zA-Z0-9.-]+$/', $_POST['new_build_name'])) {
    die('{"status":"error","message":"Malformed new_build_name"}');
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

$addbuildq = $db->execute("
    INSERT INTO builds (name,minecraft,java,mods,modpack,loadertype,memory,clients) 
        SELECT '{$_POST['new_build_name']}',minecraft,java,mods,{$_POST['dest_modpack_id']},loadertype,memory,clients
        FROM builds
        WHERE id = {$_POST['src_build_id']}
");
$id = $db->insert_id();

if (!$addbuildq) {
    die('{"status":"error","message":"Could not insert build with new name '.$_POST['new_build_name'].'"}');
}

// instead of getting insert_id(), we just let the db do it.
$latestq = $db->execute("
    UPDATE modpacks 
    SET latest = (
        SELECT id 
        FROM builds 
        WHERE public=1
        AND modpack = {$_POST['dest_modpack_id']}
        ORDER BY id DESC
        LIMIT 1
    )
    WHERE id = {$_POST['dest_modpack_id']}
");

if (!$latestq) {
    die('{"status":"error","message":"Could not set latest build"}');
}

$stats = $db->query("
    SELECT id,name,modpack,minecraft,java,mods
    FROM builds
    WHERE id={$id}
");
if ($stats && sizeof($stats)==1){
    $stats = $stats[0];
} else {
    die('{"status":"error","message":"Could not get info for new build"}');
}

// header("Location: ".$config->get('dir')."modpack?id=".$_POST['dest_modpack_id']);
die('{"status":"succ","message":"Successfully copied build!","details":'.json_encode($stats).'}');
