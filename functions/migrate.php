<?php
session_start();

if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

if (empty($_POST['db-type'])) {
    die('{"status":"error","message":"error missing db type"}');
}
if (empty($_POST['db-host'])) {
    die('{"status":"error","message":"error missing db host"}');
}
if (empty($_POST['db-user'])) {
    die('{"status":"error","message":"error missing db user"}');
}
if (empty($_POST['db-pass'])) {
    die('{"status":"error","message":"error missing db password"}');
}
if (empty($_POST['db-name'])) {
    die('{"status":"error","message":"error missing db name"}');
}
if (empty($_POST['solder-orig'])) {
    die('{"status":"error","message":"error missing solder orig"}');
}

$PROTO_STR = strtolower(current(explode('/', $_SERVER['SERVER_PROTOCOL']))).'://';

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}

require_once("db.php");
$db = new Db();
$db->connect(); // connect from configuration.php

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

$db_old = new Db();
$db_old->connect2($_POST['db-type'], $_POST['db-host'], $_POST['db-user'], $_POST['db-pass'], $_POST['db-name']); // connect from user-provided POST
if (!$db_old) {
    die('{"status":"error","message":"Could not connect to old database"}');
}

$url = $_SERVER['REQUEST_URI'];
if ($config->exists('protocol') && !empty($config->get('protocol'))) {
    $protocol = strtolower($config->get('protocol')).'://';
} else {
    $protocol = strtolower(current(explode('/', $_SERVER['SERVER_PROTOCOL']))).'://';
}

if (   !$db->execute("TRUNCATE `modpacks`")
    || !$db->execute("TRUNCATE `builds`")
    || !$db->execute("TRUNCATE `clients`")
    || !$db->execute("TRUNCATE `mods`")
    || !$db->execute("TRUNCATE `modpack_clients`")
    || !$db->execute("TRUNCATE `build_clients`")
    || !$db->execute("TRUNCATE `build_mods`")) {
    die('{"status":"error","message":"Could not truncate tables"}');
}

// ----- MODPACKS ----- \\
$res = $db_old->query("SELECT `name`,`slug`,`status`,`latest_build_id`,`recommended_build_id` FROM `modpacks`");
foreach ($res as $row) {
    $latestq = $db_old->query("
        SELECT `version` 
        FROM `builds` 
        WHERE `id` = {$row['latest_build_id']}
    ") ?: [];
    if (!$latestq) {
        die('{"status":"error","message":"Could not get latest version"}');
    }
    $latest = $latestq[0]['version'];

    $recommendedq = $db_old->query("
        SELECT `version` 
        FROM `builds` 
        WHERE `id` = {$row['recommended_build_id']}
    ") ?: [];
    if (!$recommendedq) {
        die('{"status":"error","message":"Could not get recommended version"}');
    }
    $recommended = $recommendedq[0]['version'];

    if ($row['status'] == "public") {
        $public = 1;
    } else {
        $public = 0;
    }

    $iconurl = $PROTO_STR.$config->get('host')."/resources/default/icon.png";
    if (!$db->execute("
        INSERT INTO `modpacks` (
            `display_name`,
            `name`,
            `public`,
            `latest`,
            `recommended`,
            `icon`
        ) 
        VALUES (
            {$db->quote($row['name'])},
            {$db->quote($row['slug'])},
            {$public},
            {$latest},
            {$recommended},
            {$db->quote($iconurl)}
        )
    ")){
        die('{"status":"error","message":"Could not insert modpack"}');
    }
}
// ----- BUILDS ----- \\
$res = $db_old->query("
    SELECT 
        `modpack_id`,
        `version`,
        `minecraft_version`,
        `status`,
        `java_version`,
        `required_memory` 
    FROM `builds`
");
foreach ($res as $row) {
    if ($row['status'] == "public") {
        $public = 1;
    } else {
        $public = 0;
    }
    if (!$db->execute("
        INSERT INTO `builds` (
            `modpack`,
            `name`,
            `public`,
            `minecraft`,
            `java`,
            `memory`
        ) 
        VALUES (
            {$row['modpack_id']},
            {$db->quote($row['version'])},
            {$public},
            {$db->quote($row['minecraft_version'])},
            {$db->quote($row['java_version'])},
            {$row['memory']}
        )
    ")){
        die('{"status":"error","message":"Could not insert build"}');
    }
}
// ----- CLIENTS ----- \\
$res = $db_old->query("
    SELECT 
        `title`,
        `token` 
    FROM `clients`
");
foreach ($res as $row) {
    if (!$db->execute("
        INSERT INTO `clients` (
            `name`,
            `UUID`
        ) VALUES (
            {$db->quote($row['title'])},
            {$db->quote($row['token'])}
        )
    ")){
        die('{"status":"error","message":"Could not add client"}');
    }
}
// ----- MODS ----- \\
$res = $db_old->query("
    SELECT * 
    FROM `releases`
");
foreach ($res as $row) {
    $url = $protocol.$config->get('host').$config->get('dir')."mods/".end(explode("/", $row['path']));
    $packageres = $db_old->query("
        SELECT * 
        FROM `packages` 
        WHERE `id` = {$row['package_id']}
    ");
    if (!$packageres) {
        die('{"status":"error","message":"Package id does not exist"}');
    }

    $package = $packageres[0];
    if (!$db->execute("
        INSERT INTO `mods` (
            `type`,
            `url`,
            `version`,
            `md5`,
            `filename`,
            `name`,
            `pretty_name`,
            `author`,
            `link`,
            `donlink`,
            `description`
        ) 
        VALUES (
            'mod',
            {$db->quote($url)},
            {$db->quote($row['version'])},
            {$db->quote($row['md5'])},
            {$db->quote(end(explode('/', $row['path'])))},
            {$db->quote($package['slug'])},
            {$db->quote($package['name'])},
            {$db->quote($package['author'])},
            {$db->quote($package['website_url'])},
            {$db->quote($package['donation_url'])},
            {$db->quote(trim($package['description']))}
        )
    ")){
        die('{"status":"error","message":"Could not add mod"}');
    }
    copy($_POST['solder-orig']."/storage/app/public/".$row['path'], dirname(dirname(__FILE__))."/mods/".end(explode("/", $row['path'])));

}
// ----- BUILD_RELEASE ----- \\
$res = $db_old->query("
    SELECT * 
    FROM `build_release`
");
foreach ($res as $row) {
    $mres = $db_old->query("
        SELECT `mods` 
        FROM `builds` 
        WHERE `id` = {$row['build_id']}
    ");
    if (!$mres) {
        die('{"status":"error","message":"Could not get mods column for build"}');
    }

    $ma = $mres[0];
    $ml = $ma['mods'] ? explode(',', $ma['mods']) : [];

    foreach ($ml as $modid) {
        if (empty($modid)) {
            continue;
        }
        if (!$db->execute("
            INSERT INTO build_mods (
                build_id,
                mod_id
            ) 
            VALUES (
                {$row['build_id']},
                {$modid}
            )
        ")){
        die('{"status":"error","message":"Could not insert into build_mods"}');
        }
    }
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

die('{"status":"succ","message":"Migration complete"}');
