<?php

session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}

require_once('sanitize.php');

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->mods_edit()) {
    die('Insufficient permission!');
}
if (empty($_POST['name'])) {
    die("name not specified.");
}
if (empty($_POST['pretty_name'])) {
    die("pretty_name not specified.");
}
if (empty($_POST['description'])) {
    die("description not specified.");
}
if (empty($_POST['author'])) {
    die("author not specified.");
}

if (strpbrk($_POST['name'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed name"}');
}
if (strpbrk($_POST['pretty_name'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed pretty_name"}');
}
if (strpbrk($_POST['description'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed description"}');
}
if (strpbrk($_POST['author'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed author"}');
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

$existsq = $db->query("SELECT 1 FROM mods WHERE name='{$db->sanitize($_POST['name'])}' LIMIT 1");
if (!$existsq) {
    die("mod by that name does not exist");
}

$updateq = $db->execute("UPDATE `mods` SET 
    pretty_name = '{$db->sanitize($_POST['pretty_name'])}',
    description = '{$db->sanitize($_POST['description'])}',
    author = '{$db->sanitize($_POST['author'])}'
    WHERE `name` = '{$db->sanitize($_POST['name'])}'
");
if (!$updateq) {
    die("could not update mod details.");
}

if ($_POST['submit'] == "Save and close") {
    header("Location: ".$config->get('dir')."lib-mods");
} else {
    header("Location: ".$config->get('dir')."mod?id=".$_POST['name']);
}
exit();
