<?php
// TODO: this is horribly outdated
session_start();
$config = require("./config.php");

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (empty($_POST['db-pass'])) {
    die("error");
}
if (empty($_POST['db-name'])) {
    die("error");
}
if (empty($_POST['db-user'])) {
    die("error");
}
if (empty($_POST['db-host'])) {
    die("error");
}
if (empty($_POST['solder-orig'])) {
    die("error");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("error");
}
$PROTO_STR = strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL']))).'://';

require_once("db.php");
$db=new Db;
$db->connect(); // connect from config.php

$db2=new Db;
$db2->connect2($_POST['db-type'], $_POST['db-host'],$_POST['db-user'],$_POST['db-pass'],$_POST['db-name']); // connect from user-provided POST
if (!$db2) {
    die("error");
}

$db->execute("TRUNCATE `modpacks`");
$db->execute("TRUNCATE `builds`");
$db->execute("TRUNCATE `clients`");
$db->execute("TRUNCATE `mods`");

// ----- MODPACKS ----- \\
$res = $db2->query("SELECT `name`,`slug`,`status`,`latest_build_id`,`recommended_build_id` FROM `modpacks`");
foreach ($res as $row) {
    $latest = ($db2->query("select `version` FROM `builds` WHERE `id` = ".$row['latest_build_id']))['version'];
    $recommended = ($db2->query("select `version` FROM `builds` WHERE `id` = ".$row['recommended_build_id']))['version'];
    if ($row['status'] == "public") {
        $public = 1;
    } else {
        $public = 0;
    }
    $db->execute("INSERT INTO `modpacks` (`display_name`,`name`,`public`,`latest`,`recommended`,`icon`) VALUES ('".$row['name']."','".$row['slug']."',".$public.",'".$latest."','".$recommended."','".$PROTO_STR.$config['host']."/resources/default/icon.png')");
}
// ----- BUILDS ----- \\
$res = $db2->query("SELECT `modpack_id`,`version`,`minecraft_version`,`status`,`java_version`,`required_memory` FROM `builds`");
foreach ($res as $row) {
    if ($row['status'] == "public") {
        $public = 1;
    } else {
        $public = 0;
    }
    $db->execute("INSERT INTO `builds` (`modpack`,`name`,`public`,`minecraft`,`java`,`memory`) VALUES ('".$row['modpack_id']."','".$row['version']."',".$public.",'".$row['minecraft_version']."','".$row['java_version']."','".$row['memory']."')");
}
// ----- CLIENTS ----- \\
$res = $db2->query("SELECT `title`,`token` FROM `clients`");
foreach ($res as $row) {
    $db->execute("INSERT INTO `clients` (`name`,`UUID`) VALUES ('".$row['title']."','".$row['token']."')");
}
// ----- MODS ----- \\
$res = $db2->query("SELECT * FROM `releases`");
foreach ($res as $row) {
    $url = "http://".$config['host'].$config['dir']."mods/".end(explode("/",$row['path']));
    $packageres = $db2->query("SELECT * FROM `packages` WHERE `id` = ".$row['package_id']);
    if ($packageres) {
        assert(sizeof($packageres)==1);
        $package = $packageres[0];
    }
    $db->execute("INSERT INTO `mods` (`type`,`url`,`version`,`md5`,`filename`,`name`,`pretty_name`,`author`,`link`,`donlink`,`description`) VALUES ('mod','".$url."','".$row['version']."','".$row['md5']."','".end(explode("/",$row['path']))."','".$package['slug']."','".$package['name']."','".$package['author']."','".$package['website_url']."','".$package['donation_url']."','".$package['description']."')");
    copy($_POST['solder-orig']."/storage/app/public/".$row['path'], dirname(dirname(__FILE__))."/mods/".end(explode("/",$row['path'])));
}
// ----- BUILD_RELEASE ----- \\
$res = $db2->query("SELECT * FROM `build_release`");
foreach ($res as $row) {
    $mods = [];
    $mres = $db->query("SELECT `mods` FROM `builds` WHERE `id` = ".$row['build_id']);
    if ($mres) {
        assert(sizeof($mres)==1);
        $ma = $mres[0];
    }
    $ml = explode(',', $ma['mods']);
    if (count($ml)>0) {
        array_push($mods, implode(',',$ml));
    }
    array_push($mods, $row['release_id']);
    $db->execute("UPDATE `builds` SET `mods` = '". implode(',',$mods)."' WHERE `id` = ".$row['build_id']);
}

exit();