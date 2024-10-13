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


$querybuilds = $db->query("SELECT id,name,mods FROM builds");

$warn='';

//potentially destructive..
$modq = $db->query("SELECT * FROM `mods` WHERE `name` = '".$db->sanitize($_GET['id'])."'");
foreach ($modq as $mod) {
    if ($querybuilds&&sizeof($querybuilds)>0) {
        if (!empty($querybuilds[0]['mods'])) {
            $mods=explode(',', $querybuilds[0]['mods']);
        }
    }
    if (!empty($mods)&&in_array($mod['id'], $mods)) {
        $warn.='Cannot delete version '.$mod['version'].' as it is used in build <a href=\'build?id='.$querybuilds[0]['id'].'\'>'.$querybuilds[0]['name'].'</a>';
        error_log($warn);
        continue;
    } else {
        unlink("../".$mod['type']."s/".$mod['filename']);
        $db->execute("DELETE FROM `mods` WHERE `id` = '".$mod['id']."'");
    }
}

// $db->execute("DELETE FROM `mods` WHERE `name` = '".$db->sanitize($_GET['id'])."'");

if ($warn!=='') {
    die('{"status":"warn","message":"'.$warn.'"}');
}
die('{"status":"succ","message":"Deleted successfully"}');
exit();
