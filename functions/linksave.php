<?php
session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 4, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}
if (empty($_POST['id'])) {
    die("Mod not specified.");
}
if (empty($_POST['value'])) {
    die('Value (donation link) not specified.');
}
if (!is_numeric($_POST['id'])) {
    die('Malformed id');
}
if (!filter_var($_POST['value'], FILTER_VALIDATE_URL)){
    die("Malformed value (donation link)");
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

$db->execute("UPDATE `mods` SET `link` = '{$_POST['value']}' WHERE `name` = '{$_POST['id']}'");

exit();
