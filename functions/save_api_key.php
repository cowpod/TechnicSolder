<?php
die("This script is disabled.");

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
if (strlen($api_key) != 32 && strlen($api_key) != 0) {
    die('{"status":"error", "message":"invalid api_key provided"}');
}

if (!$perms->privileged()) {
    die('{"status":"error", "message":"Insufficient permission!"}');
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}

$config->set('api_key', $api_key);
die('{"status":"succ", "message":"successfuly set api_key"}');


