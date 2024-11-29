<?php
session_start();
require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 4, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
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

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$existsq=$db->query("SELECT 1 FROM mods WHERE name='{$db->sanitize($_POST['name'])}' LIMIT 1");
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

if ($_POST['submit']=="Save and close") {
    header("Location: ".$config->get('dir')."lib-mods");
} else {
    header("Location: ".$config->get('dir')."mod?id=".$_POST['name']);
}
exit();