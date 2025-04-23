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
if (!preg_match('/[\w\-\.]+/',$_GET['name'])) {
    die("Malformed name");
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}
require_once("db.php");
$db=new Db;
$db->connect();

$name = strtolower($_GET['name']);
$id = $_GET['id'];

$nameexistsq = $db->query("SELECT 1 FROM builds WHERE name = '{$name}' AND modpack = {$id} LIMIT 1");
if ($nameexistsq) {
    die("Build with name {$_GET['name']} already exists");
}

$public_build = 0;
$addbuild = $db->execute("INSERT INTO builds (name, modpack, public) VALUES ('{$name}', '{$id}', {$public_build})");
if (!$addbuild) {
    die("Could not add build. Possibly a constraint issue?");
}

$db->disconnect();
header("Location: {$config->get('dir')}modpack?id={$id}");
exit();
