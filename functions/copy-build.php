<?php
session_start();

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 1, 1)!=="1") {
    echo '"{"status":"error","message":"Insufficient permission!"}';
    exit();
}

if (empty($_GET['modpackid'])) {
    die("Modpack ID not specified.");
}
if (empty($_GET['buildid'])) {
    die("Build ID not specified.");
}
if (empty($_GET['newname'])) {
    die("New name not specified.");
}

if (!is_numeric($_GET['modpackid'])) {
    die("Malformed id");
}
if (!is_numeric($_GET['buildid'])) {
    die("Malformed build");
}
if (!preg_match('/^[a-zA-Z0-9.-]+$/', $_GET['newname'])) {
    die("Malformed newname");
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
    INSERT INTO builds (`name`,`minecraft`,`java`,`mods`,`modpack`,loadertype,memory,clients) 
    SELECT '{$_GET['newname']}',`minecraft`,`java`,`mods`,`modpack`,loadertype,memory,clients
    FROM `builds` 
    WHERE `id` = {$_GET['buildid']}
");
if (!$addbuildq) {
    die("Could not insert build with new name {$_GET['newname']}");
}

// instead of getting insert_id(), we just let the db do it.
$latestq = $db->execute("
    UPDATE modpacks 
    SET latest = (
        SELECT id 
        FROM builds 
        WHERE public=1
        AND modpack = {$db->sanitize($_GET['modpackid'])}
        ORDER BY id DESC
        LIMIT 1
    )
    WHERE id = {$_GET['modpackid']}
");

if (!$latestq) {
    die("Could not set latest build");
}

header("Location: ".$config->get('dir')."modpack?id=".$_GET['modpackid']);
exit();
