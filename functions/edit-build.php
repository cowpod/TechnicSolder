<?php

session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('sanitize.php');

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->build_edit()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (empty($_POST['id'])) {
    die('{"status":"error","message":"id (build id) not specified"}');
}
if (empty($_POST['versions'])) {
    die('{"status":"error","message":"versions not specified"}');
}
if (empty($_POST['forgec'])) {
    die('{"status":"error","message":"forgec not specified"}');
}
if (empty($_POST['java'])) {
    die('{"status":"error","message":"java not specified"}');
}
if (empty($_POST['memory'])) {
    // die("memory not specified");
    $_POST['memory'] = '2048';
}

if (empty($_POST['ispublic']) || $_POST['ispublic'] != 'on') {
    $_POST['ispublic'] = 'off';
}

if (!is_numeric($_POST['id'])) {
    die('{"status":"error","message":"Malformed id"}');
}
if (!is_numeric($_POST['memory'])) {
    die('{"status":"error","message":"Malformed memory"}');
}
if (strpbrk($_POST['versions'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed versions"}');
}
if (strpbrk($_POST['forgec'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed forgec"}');
}
if (strpbrk($_POST['java'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed java"}');
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}

global $db;
require_once("db.php");
if (!isset($db)) {
    $db = new Db();
    $db->connect();
}

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

// set or update modloader
if ($_POST['forgec'] !== 'none') {
    if ($_POST['forgec'] === 'change') {
        if ($config->get('db-type') === 'sqlite') {
            if (!$db->execute("
                WITH mod_ids AS (
                    SELECT bm.mod_id id
                    FROM build_mods bm
                    JOIN mods m
                        ON m.id = bm.mod_id
                    WHERE bm.build_id = {$_POST['id']}
                        AND m.type = 'forge'
                )
                DELETE FROM build_mods
                WHERE mod_id IN (
                    SELECT id 
                    FROM mod_ids
                )
            ")) {
                die('{"status":"error","message":"Could not remove loader from build '.$_POST['id'].'"}');
            }
        } else {
            if (!$db->execute("
                DELETE bm
                FROM build_mods bm
                JOIN mods m 
                    ON m.id = bm.mod_id
                WHERE bm.build_id = {$_POST['id']}
                  AND m.type = 'forge';
            ")) {
                die('{"status":"error","message":"Could not remove loader from build '.$_POST['id'].'"}');
            }
        }
    } elseif ($_POST['forgec'] === 'wipe') {
       if (!$db->execute("
            DELETE FROM build_mods
            WHERE build_id = {$_POST['id']}
        ")) {
            die('{"status":"error","message":"Could not wipe build '.$_POST['id'].'"}');
        }
    }
    if (!$db->execute("
        INSERT INTO build_mods (build_id,mod_id)
        VALUES ({$_POST['id']},{$_POST['versions']})
    ")) {
        die('{"status":"error","message":"Could not set forge version in build '.$_POST['id'].'"}');
    }
}

// $minecraft = $db->query("SELECT * FROM mods WHERE type = 'forge'");
// if (!$minecraft){
//     die('{"status":"error","message":"Could not get minecraft mod"}');
// }
// $minecraft = $minecraft[0];

$ispublic = $_POST['ispublic'] == "on" ? 1 : 0;

// check if user has permission to change public
$publicq = $db->query("SELECT public FROM builds WHERE id = ".$db->sanitize($_POST['id']));
if ($publicq && sizeof($publicq) == 1 && !empty($publicq[0])) {
    if (!empty($publicq[0]['public']) && $publicq[0]['public'] != $ispublic) {
        if (!$perms->build_publish()) {
            die('{"status":"error","message":"Insufficient permission!"}');
        }
    }
}

// actually update build
if ($config->get('db-type') === 'sqlite') {
    if (!$db->execute("
        WITH loader_mod AS (
            SELECT m.mcversion,m.loadertype
            FROM build_mods bm
            JOIN mods m
            ON m.id = bm.mod_id
            WHERE bm.build_id = {$_POST['id']}
                AND m.type = 'forge'
            LIMIT 1 -- i guess it's possible to have multiple type='forge' mods...
        )
        UPDATE builds 
        SET
            minecraft = (SELECT mcversion FROM loader_mod),
            java = '{$_POST['java']}',
            memory = '{$_POST['memory']}',
            `public` = {$ispublic},
            loadertype = (SELECT loadertype FROM loader_mod)
        WHERE id = {$_POST['id']}
    ")){
        die('{"status":"error","message":"Could not update build"}');
    }
} else {
    if (!$db->execute("
        UPDATE builds b
        LEFT JOIN (
            SELECT bm.build_id, m.mcversion, m.loadertype
            FROM build_mods bm
            JOIN mods m ON m.id = bm.mod_id
            WHERE bm.build_id = {$_POST['id']}
                AND m.type = 'forge'
            LIMIT 1
        ) lm 
            ON lm.build_id = b.id
        SET
            b.minecraft = lm.mcversion,
            b.java = '{$_POST['java']}',
            b.memory = '{$_POST['memory']}',
            b.`public` = {$ispublic},
            b.loadertype = lm.loadertype
        WHERE b.id = {$_POST['id']};
    ")){
        die('{"status":"error","message":"Could not update build"}');
    }
}

// set latest public build to this one
// sqlite does not support update join
if ($ispublic) {
    if (!$db->execute("
        UPDATE modpacks
        SET latest = {$_POST['id']}
        WHERE id = (
            SELECT modpack id
            FROM builds
            WHERE id = {$_POST['id']}
        )
    ")){
        die('{"status":"error","message":"Could not set public"}');
    }
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

die('{"status":"succ","message":"Build details updated."}');
