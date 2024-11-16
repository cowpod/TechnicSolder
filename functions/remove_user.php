<?php
session_start();
require_once('./config.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

if (empty($_POST['id'])) {
    die("id not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired.");
}
if (!$_SESSION['privileged']) {
    die("Insufficient permission!");
}

require_once("db.php");
$db=new Db;
$db->connect();

$sql = $db->execute("DELETE FROM `users` WHERE `id` = ".$_POST['id']);

$db->disconnect();

if ($sql) {
    echo '<span class="text-success">User removed.</span>';
} else {
    echo '<span class="text-danger">An error has occured</span>';
}

exit();