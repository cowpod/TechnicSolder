<?php
session_start();

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

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}
if (substr($_SESSION['perms'], 3, 1)!=="1") {
    die('{"status":"error","message":"Insufficient permission!"}');
}
require_once('./config.php');
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

$name = $db->sanitize($_POST['name']);
$md5 = $db->sanitize($_POST['md5']);
$filesize = $db->sanitize($_POST['filesize']);
$link = isset($_POST['link']) ? $db->sanitize($_POST['link']) : '';
$auth = isset($_POST['author']) ? $db->sanitize($_POST['author']) : '';
$desc = isset($_POST['description']) ? $db->sanitize($_POST['description']) : '';
$donlink = isset($_POST['donlink']) ? $db->sanitize($_POST['donlink']) : '';

// we use name (slug) and md5 to determine if its already installed.
// since we have md5. otherwise we should check version,mcversion,name/slug,type,loadertype
$existsq = $db->query("SELECT 1 FROM mods WHERE name='{$name}' AND md5='{$md5}'");
if ($existsq && sizeof($existsq)>=1) {
    die('{"status":"succ","message":"Mod is already added."}');
}

$addq = $db->execute("INSERT INTO `mods`
    (`name`, `pretty_name`, `md5`, `filesize`, `url`, `link`, `author`, `donlink`, `description`, `version`, `mcversion`, `type`, `loadertype`) VALUES ( 
        '{$name}',
        '{$db->sanitize($_POST['pretty_name'])}',
        '{$md5}',
        '{$filesize}',
        '{$db->sanitize($_POST['url'])}',
        '{$link}',
        '{$auth}',
        '{$donlink}',
        '{$desc}',
        '{$db->sanitize($_POST['version'])}',
        '{$db->sanitize($_POST['mcversion'])}',
        'mod',
        '{$db->sanitize($_POST['loadertype'])}'
        )");
if ($addq) {
    die('{"status":"succ","message":"Mod successfully added."}');
}

die('{"status":"error","message":"Could not add mod."}');
