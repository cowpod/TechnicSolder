<?php
header('Content-Type: application/json');
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}
require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->clients_delete()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}
if (empty($_GET['id'])) {
    die('{"status":"error","message":"Id not specified."}');
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$deletex = $db->execute("DELETE FROM `clients` WHERE `id` = '".$db->sanitize($_GET['id'])."'");
if ($deletex) {
    die('{"status":"succ","message":"Client deleted"}');
} else {
    die('{"status":"error","message":"Unable to delete client"}');
}
