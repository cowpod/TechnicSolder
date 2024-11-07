<?php
header('Content-Type: application/json');
session_start();

if (empty($_GET['id'])) {
    die('{"status":"error","message":"id not specified"}');
}
if (empty($_GET['ispublic'])) {
    die('{"status":"error","message":"ispublic not specified"}');
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}
if (substr($_SESSION['perms'], 2, 1)!=="1") {
    die('{"status":"error","message":"Insufficient permission!"}');
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$db->execute("UPDATE builds SET public = ".$db->sanitize($_GET['ispublic'])." WHERE id = ".$db->sanitize($_GET['id']));

$latest_and_rec = $db->query("SELECT latest,recommended FROM modpacks WHERE latest=".$db->sanitize($_GET['id'])." OR recommended=".$db->sanitize($_GET['id']));
if ($latest_and_rec && sizeof($latest_and_rec)==1) {
    $latest_and_rec=$latest_and_rec[0];
    die('{"status": "succ", "latest":"'.$latest_and_rec['latest'].'","recommended":"'.$latest_and_rec['recommended'].'"}');
}

die('{"status": "succ"}');