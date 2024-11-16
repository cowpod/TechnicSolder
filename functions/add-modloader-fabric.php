<?php
header('Content-Type: application/json');
session_start();
require_once('./config.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

if (substr($_SESSION['perms'], 5, 1)!=="1") {
    echo '{"status":"error","message":"Insufficient permission!"}';
    exit();
}

//$link = $_GET['link'];
$version = $_GET['loader'];
$mcversion = $_GET['version'];
if (!file_exists("../forges/modpack-".$version)) {
    mkdir("../forges/modpack-".$version);
} else {
    echo '{"status":"error","message":"Folder modpack-'.$version.' already exists!"}';
    exit();
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

if (file_put_contents("../forges/modpack-".$version."/version.json", file_get_contents("https://meta.fabricmc.net/v2/versions/loader/".$mcversion."/".urlencode($version)."/profile/json"))) {
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
    $url = "http://".$config->get('host').$config->get('dir')."forges/fabric-".urlencode($version).".zip";
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

} else {
    echo '{"status":"error","message":"File download failed."}';
    unlink("../forges/modpack-".$version."/version.json");
    rmdir("../forges/modpack-".$version);
}

$db->disconnect();
exit();