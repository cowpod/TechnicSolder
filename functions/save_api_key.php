<?php
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user'])) {
    die('{"status":"error", "message":"Unauthorized request or login session has expired!"}');
}

// if (empty($_POST['api_key'])) {
//     die('{"status":"error", "message":"no api_key provided"}');
// }

$api_key=$_POST['api_key'];
if (!empty($api_key) && !ctype_alnum($api_key)) {
    die('{"status":"error", "message":"invalid api_key provided"}');
}
if (strlen($api_key)!=32 && strlen($api_key)!=0) {
    die('{"status":"error", "message":"invalid api_key provided"}');
}

if (isset($_SESSION['api_key']) && $_SESSION['api_key']==$api_key) {
    die('{"status":"succ", "message":"api_key is the same"}');
}

$config = require("config.php");

if ($_SESSION['privileged'] && isset($_POST['serverwide']) && $_POST['serverwide']==1) {
    $config['api_key'] = $api_key;
    file_put_contents('./config.php', '<?php return '.var_export($config, true).' ?>');
    die('{"status":"succ", "message":"successfuly set server-wide api_key"}');
}

if (!empty($config['api_key'])) {
    die('{"status":"error", "message":"Cannot set a user API key as a server-wide API key is already set."}');
}

require_once("db.php");
$db=new Db;
$db->connect();

require_once("user-settings.php");

set_setting('api_key', $api_key);

$db->disconnect();

die('{"status":"succ", "message":"successfuly set api_key"}');