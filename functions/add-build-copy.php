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
if (!preg_match('/^[\w\-\.]+$/', $_POST['new_build_name'])) {
    die('{"status":"error","message":"Malformed new_build_name"}');
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

// copy build
if (!$db->execute("
    INSERT INTO builds (
        name,
        minecraft,
        java,
        modpack,
        loadertype,
        memory
    )
    SELECT 
        {$db->quote($_POST['new_build_name'])},
        minecraft,
        java,
        {$_POST['dest_modpack_id']},
        loadertype,
        memory
    FROM builds
    WHERE id = {$_POST['src_build_id']}
")) {
    die('{"status":"error","message":"Could not insert build with new name '.$_POST['new_build_name'].'"}');
}
$new_build_id = $db->insert_id();

// copy build mods
if (!$db->execute("
    INSERT INTO build_mods (build_id,mod_id)
    SELECT {$new_build_id},mod_id
    FROM build_mods
    WHERE build_id = {$_POST['src_build_id']}
")) {
    die('{"status":"error","message":"Could not copy mods over to new build"}');
}

// copy build clients
if (!$db->execute("
    INSERT INTO build_clients (build_id,client_id)
    SELECT {$new_build_id},client_id
    FROM build_clients
    WHERE build_id = {$_POST['src_build_id']}
")) {
    die('{"status":"error","message":"Could not copy clients over to new build"}');
}

// set latest
if (!$db->execute("
    UPDATE modpacks 
    SET latest = {$new_build_id}
    WHERE id = {$_POST['dest_modpack_id']}
")){
    die('{"status":"error","message":"Could not set latest build"}');
}

$statsq = $db->query("
    SELECT b.id,b.name,b.modpack,b.minecraft,b.java,COUNT(bm.mod_id)
    FROM builds b
    JOIN build_mods bm
        ON b.id = bm.build_id
    WHERE b.id = {$new_build_id}
");
if ($statsq === false) {
    die('{"status":"error","message":"Could not get info for new build"}');
}
$stats = $statsq[0];

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

$json = @json_encode($stats);
if ($json === false) {
    $json = '';
    error_log("add-build-copy.php: could not encode stats");
}

// header("Location: ".$config->get('dir')."modpack?id=".$_POST['dest_modpack_id']);
die('{"status":"succ","message":"Successfully copied build!","details":'.$json.'}');
