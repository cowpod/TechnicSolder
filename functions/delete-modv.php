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

require("db.php");
$db=new Db;
$db->connect();

$modq = $db->query("SELECT * FROM `mods` WHERE `id` = '".$db->sanitize($_GET['id'])."'");
$mod = ($modq);
unlink("../".$mod['type']."s/".$mod['filename']);
$db->query("DELETE FROM `mods` WHERE `id` = '".$db->sanitize($_GET['id'])."'");
exit();
