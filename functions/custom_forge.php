<?php
header('Content-Type: application/json');
session_start();
$config = require("config.php");

if (substr($_SESSION['perms'], 5, 1)!=="1") {
    echo '{"status":"error","message":"Insufficient permission!"}';
    echo $_SESSION['perms'];
    exit();
}

$fileName = $_FILES["file"]["name"];
$fileTmpLoc = $_FILES["file"]["tmp_name"];
if (!$fileTmpLoc) {
    header("Location: ../lib-forges?errfilesize");
   // echo '{"status":"error","message":"File is too big! Check your post_max_size
    //(current value '.ini_get('post_max_size').') and upload_max_filesize
    //(current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}';
    exit();
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

require('slugify.php');

$version = slugify($_POST['version']);
$mcversion = $_POST['mcversion'];
if ($mcversion == "1.7.10-1.7.10") {
    $mcversion = "1.7.10";
}
if (!file_exists("../forges/modpack-".$version)) {
    mkdir("../forges/modpack-".$version);
} else {
    echo '{"status":"error","message":"Folder modpack-'.$version.' already exists!"}';
    exit();
}

if (move_uploaded_file($fileTmpLoc, "../forges/modpack-".$version."/modpack.jar")) {
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
    $url = "http://".$config['host'].$config['dir']."forges/forge-".$version.".zip";
    $res = $db->execute("INSERT INTO `mods`
                (`name`,`pretty_name`,`md5`,`url`,`link`,`author`,`description`,`version`,`mcversion`,`filename`,`type`)
                VALUES ('forge','Minecraft Forge (Custom)',
                        '".$md5."',
                        '".$url."',
                        'https://minecraftforge.net',
                        'LexManos',
                        'Minecraft Forge is a common open source API allowing a broad range of mods to work
                        cooperatively together. Is allows many mods to be created without them editing the main
                        Minecraft Code',
                        '".$version."',
                        '".$mcversion."',
                        'forge-".$version.".zip',
                        'forge')"
    );
    if ($res) {
        echo '{"status":"succ","message":"Mod has been saved."}';
        header("Location: ../lib-forges?succ");
    } else {
        echo '{"status":"error","message":"Mod could not be added to database"}';
    }
} else {
    echo '{"status":"error","message":"File download failed."}';
    rmdir("../forges/modpack-".$version);
}
exit();
