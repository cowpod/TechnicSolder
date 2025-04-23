<?php
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}
require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->modpack_edit()) {
    die('{"status":"error","message":Insufficient permission!"}');
}
if (empty($_POST['id'])) {
    die('{"status":"error","message":"Modpack not specified."}');
}
if (empty($_POST['name'])) {
    die('{"status":"error","message":"Name (slug) not specified."}');
}
if (empty($_POST['display_name'])) {
    die('{"status":"error","message":"Display name not specified."}');
}

$ispublic = (isset($_POST['ispublic']) && $_POST['ispublic']=="on") ? 1 : 0;

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

$db->execute("UPDATE modpacks
    SET name = '{$db->sanitize($_POST['name'])}',
        display_name = '{$db->sanitize($_POST['display_name'])}',
        public = {$ispublic}
    WHERE id= {$_POST['id']}
");

die('{"status":"succ","message":"Modpack details updated."}');
