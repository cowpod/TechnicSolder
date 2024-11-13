<?php
header('Content-Type: application/json');
session_start();
$config = require("config.php");

if (substr($_SESSION['perms'], 5, 1)!=="1") {
    echo '{"status":"error","message":"Insufficient permission!"}';
    exit();
}
if (!isset($_GET['dl'])) {
    die('{"status":"error","message":"dl missing"}');
}
if (!isset($_GET['version'])) {
    die('{"status":"error","message":"version missing"}');
}
if (!isset($_GET['mcversion'])) {
    die('{"status":"error","message":"mcversion missing"}');
}
if (!isset($_GET['type'])) {
    die('{"status":"error","message":"type missing"}');
} elseif (!in_array($_GET['type'],['fabric','forge','neoforge'])) {
    die('{"status":"error","message":"type is not valid"}');
}

$download_link = $_GET['dl'];
$version = $_GET['version'];
$mcversion = $_GET['mcversion'];
$type = $_GET['type'];

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

$type_pretty_names=["forge"=>"Forge","neoforge"=>"Neoforge","fabric"=>"Fabric"];
$type_links=["forge"=>"https://minecraftforge.net","neoforge"=>"https://neoforged.net","fabric"=>"https://fabricmc.net"];
$type_authors=["forge"=>"LexManos, cpw, RainWarrior, and others","neoforge"=>"LexManos, cpw, RainWarrior, and others","fabric"=>"modmuss50, technici4n, Grondag, and others."];
$type_descriptions=["forge"=>"Minecraft Forge is a free, open-source modding API and loader designed to simplify compatibility between community-created game mods in Minecraft: Java Edition. The end-user (player) must play the version of Minecraft that Forge was downloaded for before it can be used. For example, Forge 1.12 needs the 1.12 version of Minecraft in order to run correctly. Forge can then be used from the Play drop-down in the Minecraft launcher.","neoforge"=>"NeoForge is a free, open-source, community-oriented modding API for Minecraft. (This is a forked version of Forge, starting from Minecraft 1.20.1)","fabric"=>"Fabric is a modular, lightweight mod loader for Minecraft"];

if (file_put_contents("../forges/modpack-".$version."/modpack.jar", file_get_contents($download_link))) {
    $zip = new ZipArchive();
    if ($zip->open("../forges/forge-".$version.".zip", ZIPARCHIVE::CREATE) !== true) {
        echo '{"status":"error","message":"Could not open archive"}';
        exit();
    }
    $path = "../forges/modpack-".$version."/modpack.jar";
    $zip->addEmptyDir('bin');
    if (is_file($path)) {
        $zip->addFile($path, "bin/modpack.jar") or die ('{"status":"error","message":"Could not add file to archive"}');
    }
    $zip->close();
    unlink("../forges/modpack-".$version."/modpack.jar");
    rmdir("../forges/modpack-".$version);
    $md5 = md5_file("../forges/forge-".$version.".zip");
    $filesize=filesize("../forges/forge-".$version.".zip");
    $url = "http://".$config['host'].$config['dir']."forges/forge-".$version.".zip";

    $res = $db->execute("
        INSERT INTO `mods` (`name`,`pretty_name`,`md5`,`url`,`link`,`author`,`description`,`version`,`mcversion`,`filename`,`filesize`,`type`,`loadertype`) 
        VALUES (
            '".$type."', 
            '".$type_pretty_names[$type]."', 
            '".$md5."', 
            '".$url."', 
            '".$type_links[$type]."', 
            '".$type_authors[$type]."', 
            '".$type_descriptions[$type]."', 
            '".$version."', 
            '".$mcversion."', 
            'forge-".$version.".zip',
            ".$filesize.",
            'forge',
            '".$type."'
        )
    ");
    $id=$db->insert_id();

    if ($res) {
        echo '{"status":"succ","message":"Mod has been saved.", "id":"'.$id.'"}';
    } else {
        echo '{"status":"error","message":"Mod could not be added to database"}';
    }
} else {
    echo '{"status":"error","message":"File download failed."}';
    unlink("../forges/modpack-".$version."/modpack.jar");
    rmdir("../forges/modpack-".$version);
}

exit();