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
    $config=new Config();
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$url = $_SERVER['REQUEST_URI'];
if ($config->exists('protocol') && !empty($config->get('protocol'))) {
    $protocol = strtolower($config->get('protocol')).'://';
} else {
    $protocol = strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL']))).'://';
}

$mpq = $db->query("SELECT COUNT(*) AS count FROM modpacks WHERE name LIKE 'unnamed-modpack-%'");
$mpi = ($mpq && isset($mpq[0]['count'])) ? $mpq[0]['count']+1 : 1;

$db->execute("INSERT INTO modpacks (
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
    '{$protocol.$config->get('host').$config->get('dir')}resources/default/icon.png',
    'A5EA4C8FA53984C911A1B52CA31BC008',
    '{$prococol.$config->get('host').$config->get('dir')}resources/default/logo.png',
    '70A114D55FF1FA4C5EEF7F2FDEEB7D03',
    '{$protocol.$config->get('host').$config->get('dir')}resources/default/background.png',
    '88F838780B89D7C7CD10FE6C3DBCDD39',
    1
)");

$insert_id=$db->insert_id();

header("Location: ".$config->get('dir')."modpack?id=".$insert_id);
exit();
