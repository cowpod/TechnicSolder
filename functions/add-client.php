<?php

session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->clients_add()) {
    die('Insufficient permission!');
}

if (empty($_GET['name'])) {
    die("Name not specified.");
}
if (empty($_GET['uuid'])) {
    die("UUID not specified.");
}
if (!preg_match('/[\w\s\.\-]+/', $_GET['name'])) {
    die("Malformed name");
}
if ((strlen($_GET['uuid']) !== 36 && strlen($_GET['uuid']) !== 32) || !preg_match('/[a-fA-F0-9\-]+/', $_GET['uuid'])) {
    die("Malformed UUID");
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}

require_once("db.php");
$db = new Db();
$db->connect();

if (!$db->execute("
    INSERT INTO clients(
        `name`,
        `UUID`
    ) 
    VALUES (
        {$db->quote($_GET['name'])}, 
        {$db->quote($_GET['uuid'])}
    )
")){
    die("Could not add client");
}

header("Location: ".$config->get('dir')."clients");
exit();
