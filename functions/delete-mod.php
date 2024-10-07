<?php
header('Content-Type: application/json');
session_start();

if (empty($_GET['id'])) {
    die("Id not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 4, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$modq = $db->query("SELECT * FROM `mods` WHERE `name` = '".$db->sanitize($_GET['id'])."'");
foreach ($modq as $mod) {
    unlink("../".$mod['type']."s/".$mod['filename']);
}

$db->query("DELETE FROM `mods` WHERE `name` = '".$db->sanitize($_GET['id'])."'");

exit();
