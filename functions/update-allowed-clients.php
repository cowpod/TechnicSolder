<?php
session_start();

if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

if (empty($_POST['build_id']) && empty($_POST['modpack_id'])) {
    die('{"status":"error","message":"One or both of build or modpack id must be specified."}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);

if (!empty($_POST['build_id'])) {
    if (!is_numeric($_POST['build_id'])) {
        die('{"status":"error","message":"Malformed build id"}');
    }
    // we require both edit and publish permissions
    if (!$perms->build_edit() || !$perms->build_publish()) {
        die('{"status":"error","message":"Insufficient permission!"}');
    } 
}
if (!empty($_POST['modpack_id'])) {
    if (!is_numeric($_POST['modpack_id'])) {
        die('{"status":"error","message":"Malformed modpack id"}');
    }
    // we require both edit and publish permissions
    if (!$perms->modpack_edit() || !$perms->modpack_publish()) {
        die('{"status":"error","message":"Insufficient permission!"}');
    }
}

if (!empty($_POST['client_ids']) && !preg_match('/^[0-9,\s]*$/', $_POST['client_ids'])) {
    die('{"status":"error","message":"Malformed client ids"}');
}
if (empty($_POST['client_ids'])) {
    $client_ids = [];
} else {
    $client_ids = explode(',', $_POST['client_ids']);
}

global $db;
require_once('db.php');
if (!isset($db)) {
    $db = new Db();
    $db->connect();
}

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

if (!empty($_POST['build_id'])) {
    if (!$db->execute("DELETE FROM build_clients WHERE build_id = {$_POST['build_id']}")) {
        die('{"status":"error","message":"Could not clear clients."}');
    }
    if (!empty($client_ids)) {
        foreach ($client_ids as $client_id) {
            if (empty($client_id)) {
                die('{"status":"error","message":"Could not add client (empty)."}');
            }
            if (!$db->execute("
                INSERT INTO build_clients (build_id,client_id) 
                VALUES ({$_POST['build_id']},{$client_id})
            ")) {
                die('{"status":"error","message":"Could not add client."}');
            }
        }
    }
}
if (!empty($_POST['modpack_id'])) {
    if (!$db->execute("DELETE FROM modpack_clients WHERE modpack_id = {$_POST['modpack_id']}")) {
        die('{"status":"error","message":"Could not clear clients."}');
    }
    if (!empty($client_ids)) {
        foreach ($client_ids as $client_id) {
            if (empty($client_id)) {
                die('{"status":"error","message":"Could not add client (empty)."}');
            }
            if (!$db->execute("
                INSERT INTO modpack_clients (modpack_id,client_id) 
                VALUES ({$_POST['modpack_id']},{$client_id})
            ")) {
                die('{"status":"error","message":"Could not add client."}');
            }
        }
    }
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

die('{"status":"succ","message":"Allowed clients updated."}');
