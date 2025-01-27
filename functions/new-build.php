<?php
session_start();
require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
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

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'],1,1)!=="1") {
    die('Insufficient permission!');
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
$addbuild = $db->execute("INSERT INTO builds(name,modpack,public) VALUES ('{$name}', '{$id}', 0)");
if (!$addbuild) {
    die("Could not add build.");
}

$db->disconnect();
header("Location: {$config->get('dir')}modpack?id={$id}");
exit();
