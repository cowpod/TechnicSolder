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
if (!is_numeric($_GET['id'])) {
    die('{"status":"error","message":"Malformed id"}');
}

global $db;
require_once("db.php");
if (!isset($db)) {
    $db = new Db();
    $db->connect();
}

// remove client from all builds
if (!$db->execute("
    DELETE FROM build_clients
    WHERE client_id = {$_GET['id']}
")) {
    die('{"status":"error","message":"Unable to remove client from builds"}');
}

// remove client from all modpacks
if (!$db->execute("
    DELETE FROM modpack_clients
    WHERE client_id = {$_GET['id']}
")) {
    die('{"status":"error","message":"Unable to remove client from modpacks"}');
}

// remove client
if (!$db->execute("
    DELETE FROM clients
    WHERE id = {$_GET['id']}
")) {
    die('{"status":"error","message":"Unable to delete client"}');
}

die('{"status":"succ","message":"Client deleted"}');