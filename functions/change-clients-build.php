<?php
session_start();

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 1, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

if (empty($_GET['id'])) {
    die("Id (modpack) not specified.");
}
if (empty($_GET['client'])) {
    die("Client(s) not specified");
}
if (is_numeric($_GET['id'])) {
    die("Malformed id");
}
if (!preg_match('/^[a-fA-F0-9\-,]+$/', $_GET['client'])) {
    // we'll check each uuid later
    die ("Malformed clients (UUIDs seperated by comma)");
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

$clients = implode(",", $_GET['client']);

// check uuids
for ($clients as $client) {
    if (strlen($client)!==36||!preg_match('/^[a-fA-F0-9\-]+$/', $client)) {
        die("Malformed client UUID: '{$client}'");
    }
}

$db->execute("UPDATE `builds` SET `clients` = '{$clients}' WHERE `id` = {$_GET['id']}");

header("Location: {$config->get('dir')}build?id={$_GET['id']}");
exit();
