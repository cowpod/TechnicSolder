<?php

header('Content-Type: application/json');
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->modloaders_upload()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (!isset($_POST['version'])) {
    die('version missing!');
}
if (!isset($_POST['mcversion'])) {
    die('mcversion missing!');
}
if (!isset($_FILES['file']) || !isset($_FILES["file"]["name"]) || !isset($_FILES["file"]["tmp_name"])) {
    die('file missing!');
}
if (!isset($_POST['type'])) {
    die('type missing!');
} elseif (!in_array($_POST['type'], ['fabric','forge','neoforge'])) {
    die('type is invalid! only accepted are fabric,forge,neoforge');
}

if (!preg_match('/^[\w\-\.\+]+$/', $_POST['version'])) {
    die('{"status":"error","message":"Malformed version"}');
}
if (!preg_match('/^[\w\-\.\+]+$/', $_POST['mcversion'])) {
    die('{"status":"error","message":"Malformed mcversion"}');
}

$fileTmpLoc = $_FILES["file"]["tmp_name"];
if (!$fileTmpLoc) {
    header("Location: ../modloaders?errfilesize");
    // echo '{"status":"error","message":"File is too big! Check your post_max_size
    //(current value '.ini_get('post_max_size').') and upload_max_filesize
    //(current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}';
    // exit();
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

$url = $_SERVER['REQUEST_URI'];
if ($config->exists('protocol') && !empty($config->get('protocol'))) {
    $protocol = strtolower($config->get('protocol')).'://';
} else {
    $protocol = strtolower(current(explode('/', $_SERVER['SERVER_PROTOCOL']))).'://';
}

require('slugify.php');

$version = slugify($_POST['version']);
$mcversion = $_POST['mcversion'];
$type = $_POST['type'];

if (is_dir("../forges/modpack-".$version)) {
    die('{"status":"error","message":"Folder modpack-'.$version.' already exists!"}');
}

mkdir("../forges/modpack-".$version);

if (!move_uploaded_file($fileTmpLoc, "../forges/modpack-".$version."/modpack.jar")) {
    rmdir("../forges/modpack-".$version);
    die('{"status":"error","message":"File download failed."}');
}

$zip = new ZipArchive();
if ($zip->open("../forges/forge-".$version.".zip", ZIPARCHIVE::CREATE) !== true) {
    die('{"status":"error","message":"Could not open archive"}');
}
$path = "../forges/modpack-".$version."/modpack.jar";
$zip->addEmptyDir('bin');
if (is_file($path)) {
    $zip->addFile($path, "bin/modpack.jar");
} else {
    $zip->close();
    die('{"status":"error","message":"Could not find file to add to archive"}');
}
$zip->close();

unlink("../forges/modpack-".$version."/modpack.jar");
rmdir("../forges/modpack-".$version);

$author = $_SESSION['name'];
$filename = "forge-{$version}.zip";
$md5 = md5_file("../forges/{$filename}");
$file_size = filesize("../forges/{$filename}");
$url = $protocol.$config->get('host').$config->get('dir')."forges/forge-".$version.".zip";
if (!$db->execute(
    "INSERT INTO mods (
        name,
        pretty_name,
        md5,
        url,
        link,
        author,
        description,
        version,
        mcversion,
        filename,
        filesize,
        type,
        loadertype
    )
    VALUES (
        'customloader',
        'Custom mod loader',
        {$db->quote($md5)},
        {$db->quote($url)},
        '',
        {$db->quote($author)},
        'Custom mod loader',
        {$db->quote($version)},
        {$db->quote($mcversion)},
        {$db->quote($filename)},
        {$file_size}
        'forge',
        {$db->quote($type)}
    )"
)){
    die('{"status":"error","message":"Mod could not be added to database"}');
}

header("Location: ../modloaders?succ");