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
require("db.php");
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
$db->query("INSERT INTO modpacks(`name`,
                     `display_name`,
                     `icon`,
                     `icon_md5`,
                     `logo`,
                     `logo_md5`,
                     `background`,
                     `background_md5`,
                     `public`,
                     `recommended`,
                     `latest`)
    VALUES ('".$mpname."',
    '".$mpdname."',
    'http://".$config['host'].$config['dir']."resources/default/icon.png',
    'A5EA4C8FA53984C911A1B52CA31BC008',
    'http://".$config['host'].$config['dir']."resources/default/logo.png',
    '70A114D55FF1FA4C5EEF7F2FDEEB7D03',
    'http://".$config['host'].$config['dir']."resources/default/background.png',
    '88F838780B89D7C7CD10FE6C3DBCDD39',
    1,
    '1.0',
    '1.0'
    )"
);
$mpq = $db->query("SELECT `id` FROM `modpacks` ORDER BY `id` DESC LIMIT 1");
$mp = ($mpq);
$mpi =  intval($mp['id']);
$fq = $db->query("SELECT `mcversion` FROM `mods` WHERE `id` = ". $bforge);
$f = ($fq);
$minecraft =  $f['mcversion'];
$db->query("INSERT INTO builds(`name`,`modpack`,`public`,`mods`,`java`,`memory`,`minecraft`)
    VALUES ('1.0','".$mpi."',1,'".$bforge.",".$bmods."','".$bjava."','".$bmemory."','".$minecraft."')"
);
header("Location: ".$config['dir']."modpack?id=".$mpi);
exit();
