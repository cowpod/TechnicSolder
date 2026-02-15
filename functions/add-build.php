<?php

session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}


require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->build_create()) {
    die('Insufficient permission!');
}

if (empty($_GET['id'])) {
    die("Modpack ID not specified.");
}
if (empty($_GET['name'])) {
    die("Build name not specified.");
}
if (!is_numeric($_GET['id'])) {
    die("Malformed id");
}
if (!preg_match('/[\w\-\.]+/', $_GET['name'])) {
    die("Malformed name");
}

$name = strtolower($_GET['name']);
$id = $_GET['id'];

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}
require_once("db.php");
$db = new Db();
$db->connect();

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

$nameexistsq = $db->query("
    SELECT 1 
    FROM builds 
    WHERE name = {$db->quote($name)} 
    AND modpack = {$id} 
    LIMIT 1
");
if ($nameexistsq) {
    die("Build with name {$_GET['name']} already exists");
}

if(!$db->execute("
    INSERT INTO builds (
        name, 
        modpack, 
        public
    ) 
    VALUES (
        {$db->quote($name)}, 
        {$id}, 
        0
    )
")) {
    die("Could not add build.");
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

header("Location: {$config->get('dir')}modpack?id={$id}");
exit();
