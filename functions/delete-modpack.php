<?php
session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->modpack_delete()) {
    die('Insufficient permission!');
}

if (empty($_GET['id'])) {
    die("Modpack not specified.");
}
if (!is_numeric($_GET['id'])) {
    die("Malformed id.");
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}

require_once("db.php");
$db = new Db();
$db->connect();

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

// delete mods for builds for modpack
if ($config->get('db-type') === 'sqlite') {
    if (!$db->execute("
        DELETE FROM build_mods
        WHERE build_id IN (
            SELECT b.id
            FROM builds b
            JOIN modpacks m
                ON m.id = b.modpack
            WHERE m.id = {$_GET['id']}
        )
    ")) {
        die('{"status":"error","message":"Could not delete mods for builds for modpack"}');
    }
    // delete clients for builds for modpack
    if (!$db->execute("
        DELETE FROM build_clients
        WHERE build_id IN (
            SELECT id
            FROM builds
            WHERE modpack = {$_GET['id']}
        )
    ")) {
        die('{"status":"error","message":"Could not delete clients for builds for  modpack"}');
    }
} else {
    if (!$db->execute("
        DELETE bm
        FROM build_mods bm
        JOIN builds b
            ON b.id = bm.build_id
        JOIN modpacks m
            ON m.id = b.modpack
        WHERE m.id = {$_GET['id']}
    ")) {
        die('{"status":"error","message":"Could not delete mods for builds for modpack"}');
    }
    // delete clients for builds for modpack
    if (!$db->execute("
        DELETE bc
        FROM build_clients bc
        JOIN builds b
            ON b.id = bc.build_id
        WHERE b.modpack = {$_GET['id']}
    ")) {
        die('{"status":"error","message":"Could not delete clients for builds for  modpack"}');
    }
}

// delete builds for modpack
if (!$db->execute("
    DELETE b
    FROM builds b
    WHERE b.modpack = {$_GET['id']}
")) {
    die('{"status":"error","message":"Could not delete builds"}');
}

// delete clients for modpack
if (!$db->execute("
    DELETE mc
    FROM modpack_clients mc
    WHERE mc.modpack_id = {$_GET['id']}
")) {
    die('{"status":"error","message":"Could not delete builds"}');
}

// delete modpack
if (!$db->execute("DELETE FROM modpacks WHERE id = {$_GET['id']}")){
    die('{"status":"error","message":"Could not delete modpack"}');
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

header("Location: ".$config->get('dir')."dashboard");
exit();
