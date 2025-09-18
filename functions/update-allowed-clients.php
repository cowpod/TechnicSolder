<?php

session_start();

if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
// can't create edit delete builds nor publish
if ($perms->build_edit() && $perms->build_publish()) {
} elseif ($perms->modpack_edit() && $perms->modpack_publish()) {
} else {
    die('{"status":"error","message":"Insufficient permission!"}');
}

// build/modpack id
if (empty($_POST['build_id']) && empty($_POST['modpack_id'])) {
    die('{"status":"error","message":"Build/modpack id not specified."}');
}
if (!empty($_POST['modpack_id']) && !empty($_POST['build_id'])) {
    die('{"status":"error","message":"Only one of build/modpack id can be specified."}');
}
if (!empty($_POST['modpack_id'])) {
    $id = $_POST['modpack_id'];
    $which_table = 'modpacks';
}
if (!empty($_POST['build_id'])) {
    $id = $_POST['build_id'];
    $which_table = 'builds';
}
if (empty($_POST['client_ids'])) {
    // allow setting no clients
    // die('Client(s) not specified');
    $client_ids = '';
} else {
    $client_ids = $_POST['client_ids'];
}

if (!is_numeric($id)) {
    die('{"status":"error","message":"Malformed id"}');
}

assert(!empty($which_table));

// client ids are checked later

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}

global $db;
require_once('db.php');
if (!isset($db)) {
    $db = new Db();
    $db->connect();
}

// check that a client by that id (not uuid) exists.
if ($client_ids !== '') {
    $client_id_arr = explode(',', $client_ids);
    foreach ($client_id_arr as $client_id) {
        if (!is_numeric($client_id)) {
            die('{"status":"error","message":"Malformed client id."}');
        }
        $client_existsx = $db->execute("SELECT 1 FROM clients where id = {$client_id}");
        if (!$client_existsx) {
            // yeah yeah we re-use db's sanitization function
            die('{"status":"error","message":"Invalid client id '.$client_id.'."}');
        }
    }
}

$setclientsx = $db->execute("UPDATE {$which_table} SET clients = '{$client_ids}' WHERE id = {$id}");
if (!$setclientsx) {
    error_log("update-allowed-clients.php: could not set allowed clients for id '{$id}'.");
    die('{"status":"error","message":"Could not set allowedclients."}');
}

die('{"status":"succ","message":"Allowed clients updated."}');
