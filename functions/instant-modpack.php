<?php
session_start();
$config = require("config.php");

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 0, 1)!=="1") {
    echo 'You do not have permission to create modpacks!';
    exit();
}
if (substr($_SESSION['perms'], 1, 1)!=="1") {
    echo 'You do not have permission to create builds!';
    exit();
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

$db->execute("INSERT INTO modpacks(`name`, `display_name`, `icon`, `icon_md5`, `logo`, `logo_md5`, `background`, `background_md5`, `public`, `recommended`, `latest`) 
    VALUES ('".$mpname."',
    '".$mpdname."',
    'http://".$config['host'].$config['dir']."resources/default/icon.png',
    'A5EA4C8FA53984C911A1B52CA31BC008',
    'http://".$config['host'].$config['dir']."resources/default/logo.png',
    '70A114D55FF1FA4C5EEF7F2FDEEB7D03',
    'http://".$config['host'].$config['dir']."resources/default/background.png',
    '88F838780B89D7C7CD10FE6C3DBCDD39',
    1,
    '',
    '')");
// latest,public will be set later in this script

// $mpq = $db->query("SELECT `id` FROM `modpacks` ORDER BY `id` DESC LIMIT 1");
// if ($mpq) {
//     assert(sizeof($mpq)==1);
//     $mp = $mpq[0];
// }
// $mpi = intval($mp['id']);
$mpi = $db->insert_id();

$fq = $db->query("SELECT `loadertype`,`mcversion` FROM `mods` WHERE `id` = ". $bforge);
if ($fq) {
    assert(sizeof($fq)==1);
    $f = $fq[0];
}
$minecraft = $f['mcversion'];
$loadertype= $f['loadertype'];
$forgeandmods = !empty($bmods) ? $bforge.','.$bmods : $bforge;
error_log("FORGEANDMODS ".$forgeandmods);
$db->execute("INSERT INTO builds(`name`,`modpack`,`public`,`mods`,`java`,`memory`,`minecraft`,`loadertype`) 
    VALUES ('1.0', '".$mpi."', 1, '".$forgeandmods."', '".$bjava."', '".$bmemory."', '".$minecraft."', '".$loadertype."')");

$new_build_id = $db->insert_id();

$db->execute("UPDATE modpacks SET latest=".$new_build_id.", recommended=".$new_build_id." WHERE id=".$mpi);

header("Location: ".$config['dir']."modpack?id=".$mpi);
exit();
