<?php
session_start();

if (empty($_SESSION['user'])) {
    die('Unauthorized request or login session has expired!');
}
if (substr($_SESSION['perms'], 1, 1)!=='1') {
    die('Insufficient permission!');
}

if (empty($_POST['build_id'])) {
    die('Id (modpack) not specified.');
}
if (empty($_POST['client_ids'])) {
    // allow setting no clients
    // die('Client(s) not specified');
    $client_ids = '';
} else {
    $client_ids = $_POST['client_ids'];
}

if (!is_numeric($_POST['build_id'])) {
    die('Malformed id');
} else {
    $build_id = $_POST['build_id'];
}
// client ids are checked later

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

global $db;
require_once('db.php');
if (!isset($db)){
    $db=new Db;
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
            die('{"status":"error","message":"Invalid client id '.$db->sanitize($client_id).'."}');
        }
    }
}

$setclientsx = $db->execute("UPDATE builds SET clients = '{$client_ids}' WHERE id = {$build_id}");
if (!$setclientsx) {
    error_log("update-build-clients.php: could not set clients for build '{$build_id}'.");
    die('{"status":"error","message":"Could not set clients."}');
}

die('{"status":"succ","message":"Build clients updated."}');
