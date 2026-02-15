<?php

session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}


require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->modpack_create()) {
    die('Insufficient permission!');
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

$url = $_SERVER['REQUEST_URI'];
if ($config->exists('protocol') && !empty($config->get('protocol'))) {
    $protocol = strtolower($config->get('protocol')).'://';
} else {
    $protocol = strtolower(current(explode('/', $_SERVER['SERVER_PROTOCOL']))).'://';
}

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

$mpq = $db->query("SELECT COUNT(*) AS count FROM modpacks WHERE name LIKE 'unnamed-modpack-%'");
$mpi = ($mpq && isset($mpq[0]['count'])) ? $mpq[0]['count'] + 1 : 1;

$base_url = "{$protocol}{$config->get('host')}{$config->get('dir')}";
$iconurl = "{$base_url}resources/default/icon.png";
$logourl = "{$base_url}resources/default/logo.png";
$backgroundurl = "{$base_url}resources/default/background.png";

if (!$db->execute("INSERT INTO modpacks (
    name,
    display_name,
    icon,
    icon_md5,
    logo,
    logo_md5,
    background,
    background_md5,
    public
) 
VALUES (
    'unnamed-modpack-{$mpi}',
    'Unnamed modpack',
    {$db->quote($iconurl)},
    'A5EA4C8FA53984C911A1B52CA31BC008',
    {$db->quote($logourl)},
    '70A114D55FF1FA4C5EEF7F2FDEEB7D03',
    {$db->quote($backgroundurl)},
    '88F838780B89D7C7CD10FE6C3DBCDD39',
    0
)")){
    die("Could not add modpack");
}

$insert_id = $db->insert_id();

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

header("Location: {$config->get('dir')}modpack?id={$insert_id}");
exit();
