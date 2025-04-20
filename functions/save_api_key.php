<?php
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user'])) {
    die('{"status":"error", "message":"Unauthorized request or login session has expired!"}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);

$api_key = $_POST['api_key'];
if (!empty($api_key) && !ctype_alnum($api_key)) {
    die('{"status":"error", "message":"invalid api_key provided"}');
}
if (strlen($api_key)!=32 && strlen($api_key)!=0) {
    die('{"status":"error", "message":"invalid api_key provided"}');
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

// set server-wide key
if ($perms->privileged() && isset($_POST['serverwide']) && $_POST['serverwide']==1) {
    $config->set('api_key', $api_key);
    die('{"status":"succ", "message":"successfuly set server-wide api_key"}');
}

if ($config->exists('api_key')) {
    die('{"status":"error", "message":"Cannot set a user API key as a server-wide API key is already set."}');
}

require_once("user-settings.php");

if (get_setting('api_key') && get_setting('api_key')==$api_key) {
    die('{"status":"succ", "message":"api_key is the same"}');
}

require_once("db.php");
$db=new Db;
$db->connect();

set_setting('api_key', $api_key);

$db->disconnect();

die('{"status":"succ", "message":"successfuly set api_key"}');