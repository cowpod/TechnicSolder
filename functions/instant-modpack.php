<?php

session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->modpack_create() || !$perms->build_create()) {
    die('{"status":"error","message":"Insufficient permission!"}');
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

$url = $_SERVER['REQUEST_URI'];
if ($config->exists('protocol') && !empty($config->get('protocol'))) {
    $protocol = strtolower($config->get('protocol')).'://';
} else {
    $protocol = strtolower(current(explode('/', $_SERVER['SERVER_PROTOCOL']))).'://';
}

$mpdname = $_POST['display_name'];
$mpname = $_POST['name'];
$bforge = $_POST['versions'];
$bjava = $_POST['java'];
$bmemory = $_POST['memory'];
$bmods = $_POST['modlist'];
$public_modpack = $perms->modpack_publish() ? 1 : 0;

if ($db->query("
    SELECT 1
    FROM modpacks
    WHERE name = {$db->quote($mpname)}
    LIMIT 1
") ?: []) {
    die('{"status":"error","message":"A modpack with that slug already exists. Please choose another."}');
}

$icon_url = "{$protocol}{$config->get('host')}{$config->get('dir')}resources/default/icon.png";
$logo_url = "{$protocol}{$config->get('host')}{$config->get('dir')}resources/default/logo.png";
$background_url = "{$protocol}{$config->get('host')}{$config->get('dir')}resources/default/background.png";
// we set recommended, latest later
if (!$db->execute("INSERT INTO modpacks (
        name, 
        display_name, 
        icon, 
        icon_md5, 
        logo, 
        logo_md5, 
        background, 
        background_md5, 
        public, 
        recommended, 
        latest
    ) 
    VALUES (
        {$db->quote($mpname)},
        {$db->quote($mpdname)},
        {$db->quote($icon_url)},
        'A5EA4C8FA53984C911A1B52CA31BC008',
        {$db->quote($logo_url)},
        '70A114D55FF1FA4C5EEF7F2FDEEB7D03',
        {$db->quote($background_url)},
        '88F838780B89D7C7CD10FE6C3DBCDD39',
        {$public_modpack},
        '',
        ''
    )")) {
    error_log("instant-modpack.php: could not add new modpack");
    die('{"status":"error","message":"Could not add new modpack."}');
}
// todo: check if modpack by that name exists.
$new_modpack_id = $db->insert_id();

$loader_modq = $db->query("
    SELECT loadertype,mcversion 
    FROM mods 
    WHERE id = {$bforge}
");
if (!$loader_modq) {
    die('{"status":"error","message":"Mod id does not exist: {$bforge}"}');
}
$loader_mod = $loader_modq[0];

$minecraft = $loader_mod['mcversion'];
$loadertype = $loader_mod['loadertype'];
$public_build = $perms->build_publish() ? 1 : 0;

if (!$db->execute("INSERT INTO builds (
        name,
        modpack,
        public,
        java,
        memory,
        minecraft,
        loadertype
    ) 
    VALUES (
        '1.0', 
        {$new_modpack_id}, 
        {$public_build}, 
        {$db->quote($bjava)}, 
        {$db->quote($bmemory)}, 
        {$db->quote($minecraft)}, 
        {$db->quote($loadertype)}
    )")) {
    error_log("instant-modpack.php: could not add new build");
    die('{"status":"error","message":"Could not add new build"}');
}
$new_build_id = $db->insert_id();

$forgeandmods = !empty($bmods) ? $bforge.','.$bmods : $bforge;
$modsarr = $forgeandmods ? explode(',', $forgeandmods) : [];

foreach ($modsarr as $mod_id) {
    if (!$db->execute("
        INSERT INTO build_mods (
            build_id, 
            mod_id
        )
        VALUES (
            {$db->quote($new_build_id)}, 
            {$db->quote($mod_id)}
        )
    ")) {
        die('{"status":"error","message":"Could not add mod {$mod_id} to build {$new_build_id}"}');
    }
}

if (!$db->execute("
    UPDATE modpacks 
    SET 
        latest = {$new_build_id}, 
        recommended = {$new_build_id}
    WHERE id = {$new_modpack_id}
")) {
    error_log("instant-modpack.php: could not set modpack build");
    die('{"status":"error","message":"Could not set modpack build"}');
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

die('{"status":"succ","message":"Modpack created","id":'.$new_modpack_id.'}');

