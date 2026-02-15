<?php

session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->mods_upload()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (empty($_POST['pretty_name'])) {
    die('{"status":"error","message":"Pretty name not specified."}');
}
if (empty($_POST['name'])) {
    die('{"status":"error","message":"Slug not specified."}');
}
if (empty($_POST['version'])) {
    die('{"status":"error","message":"Version not specified."}');
}
if (empty($_POST['url'])) {
    die('{"status":"error","message":"File URL not specified."}');
}
if (empty($_POST['md5'])) {
    die('{"status":"error","message":"MD5 not specified."}');
}
if (empty($_POST['filesize'])) {
    die('{"status":"error","message":"Filesize not specified."}');
}
if (empty($_POST['mcversion'])) {
    die('{"status":"error","message":"Minecraft version not specified."}');
}
if (empty($_POST['loadertype'])) {
    die('{"status":"error","message":"Loader type not specified."}');
}

// if (strpbrk($_POST['pretty_name'], '\\"\'') !== false) {
//     die('{"status":"error","message":"Malformed pretty_name"}');
// }
// if (strpbrk($_POST['name'], '\\"\'') !== false) {
//     die('{"status":"error","message":"Malformed name"}');
// }
if (!preg_match('/^[\w\-\.\+]+$/', $_POST['version'])) {
    die('{"status":"error","message":"Malformed version"}');
}
if (!filter_var($_POST['url'], FILTER_VALIDATE_URL)) {
    die('{"status":"error","message":"Malformed url"}');
}
if (strlen($_POST['md5']) !== 32 || !ctype_alnum($_POST['md5'])) {
    die('{"status":"error","message":"Malformed md5"}');
}
if (!is_numeric($_POST['filesize'])) {
    die('{"status":"error","message":"Malformed filesize"}');
}
if (!preg_match('/^[\w\-\.\+]+$/', $_POST['version'])) {
    die('{"status":"error","message":"Malformed mcversion"}');
}
if (!in_array($_POST['loadertype'], ['fabric','forge','neoforge'])) {
    die('{"status":"error","message":"Malformed loadertype"}');
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

$link = isset($_POST['link']) ? $_POST['link'] : '';
$auth = isset($_POST['author']) ? $_POST['author'] : '';
$desc = isset($_POST['description']) ? $_POST['description'] : '';
$donlink = isset($_POST['donlink']) ? $_POST['donlink'] : '';

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

// we use name (slug) and md5 to determine if its already installed.
// since we have md5. otherwise we should check version,mcversion,name/slug,type,loadertype
$existsq = $db->query("
    SELECT 1 
    FROM mods 
    WHERE name = {$db->quote($name)}
    AND md5 = {$db->quote($md5)}
");
if ($existsq && sizeof($existsq) >= 1) {
    die('{"status":"succ","message":"Mod is already added."}');
}

if (!$db->execute("
    INSERT INTO mods (
        name, 
        pretty_name,
        md5, 
        filesize, 
        url, 
        link, 
        author, 
        donlink, 
        description,
        version, 
        mcversion, 
        type, 
        loadertype
    ) 
    VALUES ( 
        {$db->quote($_POST['name'])},
        {$db->quote($_POST['pretty_name'])},
        {$db->quote($_POST['md5'])},
        {$_POST['filesize']},
        {$db->quote($_POST['url'])},
        {$db->quote($link)},
        {$db->quote($auth)},
        {$db->quote($donlink)},
        {$db->quote($desc)},
        {$db->quote($_POST['version'])},
        {$db->quote($_POST['mcversion'])},
        'mod',
        {$db->quote($_POST['loadertype'])}
    )
")) {
    die('{"status":"error","message":"Could not add mod."}');
}

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

die('{"status":"succ","message":"Mod successfully added."}');