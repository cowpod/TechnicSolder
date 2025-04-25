<?php
header('Content-Type: application/json');
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('sanitize.php');

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->modloaders_upload()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

//$link = $_GET['link'];
$version = $_GET['loader'];
$mcversion = $_GET['version'];

if (strpbrk($_POST['loader'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed loader"}');
}
if (strpbrk($_POST['version'], '\\"\'') !== false) {
    die('{"status":"error","message":"Malformed version"}');
}

if (!file_exists("../forges/modpack-".$version)) {
    mkdir("../forges/modpack-".$version);
} else {
    echo '{"status":"error","message":"Folder modpack-'.$version.' already exists!"}';
    exit();
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

$url = $_SERVER['REQUEST_URI'];
if ($config->exists('protocol') && !empty($config->get('protocol'))) {
    $protocol = strtolower($config->get('protocol')).'://';
} else {
    $protocol = strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL']))).'://';
}

$get_data = @file_get_contents("https://meta.fabricmc.net/v2/versions/loader/".$mcversion."/".urlencode($version)."/profile/json");
if ($get_data === false) {
    unlink("../forges/modpack-".$version."/version.json");
    rmdir("../forges/modpack-".$version);
    die('{"status":"error","message":"File download failed."}');
}

$save_data = @file_put_contents("../forges/modpack-".$version."/version.json", $get_data);
if ($save_data === false) {
    unlink("../forges/modpack-".$version."/version.json");
    rmdir("../forges/modpack-".$version);
    die('{"status":"error","message":"File save failed."}');
}

$zip = new ZipArchive();
if ($zip->open("../forges/fabric-".$version.".zip", ZIPARCHIVE::CREATE) !== TRUE) {
    echo '{"status":"error","message":"Could not open archive"}';
    exit();
}

$path = "../forges/modpack-".$version."/version.json";
$zip->addEmptyDir('bin');
if (is_file($path)) {
    $zip->addFile($path, "bin/version.json") or die ('{"status":"error","message":"Could not add file to archive"}');
}
$zip->close();
unlink("../forges/modpack-".$version."/version.json");
rmdir("../forges/modpack-".$version);

$md5 = md5_file("../forges/fabric-".$version.".zip");
$file_size=filesize("../forges/fabric-".$version.".zip");
$url = $protocol.$config->get('host').$config->get('dir')."forges/fabric-".urlencode($version).".zip";

$res = $db->execute("INSERT INTO `mods` (`name`,`pretty_name`,`md5`,`url`,`link`,`author`,`description`,`version`,`mcversion`,`filename`,`filesize`,`type`,`loadertype`) VALUES (
    'fabric',
    'Fabric (alpha)',
    '{$md5}',
    '{$url}',
    'https://fabricmc.net/',
    'FabricMC Team', 
    'Fabric is a lightweight, experimental modding toolchain for Minecraft.', 
    '{$version}',
    '{$mcversion}',
    'fabric-{$version}.zip',
    '{$file_size}',
    'forge',
    'fabric'
)");
if ($res) {
    echo '{"status":"succ","message":"Loader has been saved.", "id": '.$db->insert_id().'}';
} else {
    echo '{"status":"error","message":"Loader could not be added to database"}';
}

$db->disconnect();
exit();