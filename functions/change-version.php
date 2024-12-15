<?php
session_start();

if (empty($_GET['id_new'])) {
    die('{"status":"error","message":"New mod not specified."}');
}
if (empty($_GET['id_old'])) {
    die('{"status":"error","message":"Old mod(s) not specified."}');
}
if (empty($_GET['bid'])) {
    die('{"status":"error","message":"Build not specified."}');
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}
if (substr($_SESSION['perms'], 1, 1)!=="1") {
    die('{"status":"error","message":"Insufficient permission!"}');
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

if (!is_numeric($_GET['bid'])||!is_numeric($_GET['id_new'])||!is_numeric($_GET['id_old'])) {
    die('{"status":"error","Malformed value(s)"}');
}

$modsq = $db->query("SELECT mods FROM builds WHERE id = ".$db->sanitize($_GET['bid']));
if ($modsq && sizeof($modsq)==1 && !empty($modsq[0]['mods'])) {
    $modslist = explode(',', $modsq[0]['mods']);
    unset($modslist[array_search($_GET['id_old'], $modslist)]);
    array_push($modslist, $_GET['id_new']);
    $modslist_string = implode(',',$modslist);
} else {
    die('{"status":"error","message":"Build contains no mods. You need to set the version and loader."}');
}

$db->execute("UPDATE builds SET mods = '{$modslist_string}' WHERE id = {$db->sanitize($_GET['bid'])}");

die('{"status":"succ","message":"Version changed sucessfully"');
