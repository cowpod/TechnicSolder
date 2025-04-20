<?php
session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}
require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->modpack_create() || !$perms->build_create()) {
    die('Insufficient permission!');
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}
global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$mpdname = $db->sanitize($_POST['display_name']);
$mpname = $db->sanitize($_POST['name']);
$bmods = $db->sanitize($_POST['modlist']);
$bjava = $db->sanitize($_POST['java']);
$bmemory = $db->sanitize($_POST['memory']);
$bforge = $db->sanitize($_POST['versions']);
$public_modpack = $perms->modpack_publish() ? 1 : 0;

$addmodpackx = $db->execute("INSERT INTO modpacks (
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
        '{$mpname}',
        '{$mpdname}',
        'http://{$config->get('host').$config->get('dir')}resources/default/icon.png',
        'A5EA4C8FA53984C911A1B52CA31BC008',
        'http://{$config->get('host').$config->get('dir')}resources/default/logo.png',
        '70A114D55FF1FA4C5EEF7F2FDEEB7D03',
        'http://{$config->get('host').$config->get('dir')}resources/default/background.png',
        '88F838780B89D7C7CD10FE6C3DBCDD39',
        {$public_modpack},
        '',
        ''
    )");
if (!$addmodpackx) {
    error_log("instant-modpack.php: could not add new modpack");
    die("Could not add new modpack");
}
$mpi = $db->insert_id();

$loader_modq = $db->query("SELECT loadertype,mcversion FROM mods WHERE id={$bforge}");
if (!$loader_modq) {
    die("Mod id does not exist; {$bforge}");
}
$loader_mod = $loader_modq[0];

$minecraft = $loader_mod['mcversion'];
$loadertype = $loader_mod['loadertype'];
$forgeandmods = !empty($bmods) ? $bforge.','.$bmods : $bforge;
$public_build = $perms->build_publish() ? 1 : 0;

$addbuildx = $db->execute("INSERT INTO builds (
        name,
        modpack,
        public,
        mods,
        java,
        memory,
        minecraft,
        loadertype
    ) 
    VALUES (
        '1.0', 
        {$mpi}, 
        {$public_build}, 
        '{$forgeandmods}', 
        '{$bjava}', 
        '{$bmemory}', 
        '{$minecraft}', 
        '{$loadertype}'
    )");
if (!$addbuildx) {
    error_log("instant-modpack.php: could not add new build");
    die("Could not add new build");
}
$new_build_id = $db->insert_id();

$setmodpackbuildx = $db->execute("UPDATE modpacks SET latest='{$new_build_id}', recommended='{$new_build_id}' WHERE id={$mpi}");
if (!$setmodpackbuildx) {
    error_log("instant-modpack.php: could not set modpack build");
    die("Could not set modpack build");
}

header("Location: ".$config->get('dir')."modpack?id=".$mpi);
exit();
