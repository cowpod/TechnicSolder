<?php
session_start();
require_once('./config.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired.");
}
if (empty($_POST['pass'])) {
    die("Password not specified.");
}

function isStrongPassword($password) {
    if (strlen($password) < 8) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    return true;
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

if (!isStrongPassword($_POST['pass'])) {
    die("Bad password.");
}

$sql = $db->execute("UPDATE `users` SET `pass` = '".$pass."' WHERE `name` = '".$_SESSION['user']."'"
);
echo $db->error();

header("Location: ".$config->get('dir')."user");
exit();
