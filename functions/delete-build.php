<?php
header('Content-Type: application/json');
session_start();

if (empty($_GET['id']) || empty($_GET['pack'])) {
    die("Build not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 1, 1)!=="1") {
    die("Insufficient permission!");
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$db->execute("DELETE FROM `builds` WHERE `id` = '".$db->sanitize($_GET['id'])."'");
$bq = $db->query("SELECT * FROM `builds` WHERE `modpack` = '".$db->sanitize($_GET['pack'])."' AND `public` = 1 ORDER BY `id` DESC LIMIT 1");

if ($bq) {
    assert(sizeof($bq)==1);
    $build = $bq[0];
    $response = array(
        "exists" => true,
        "name" => $build['name'],
        "mc" => $build['minecraft']
    );
} else {
    $response = array(
        "exists" => false
    );
}

// get latest public build
$lpq = $db->query("SELECT id FROM build WHERE public = 1 AND modpack = ".$db->sanitize($_GET['pack'])." ORDER BY id DESC LIMIT 1");
if ($lpq) {
    $db->execute("UPDATE modpacks SET latest = ".$lpq[0]['id']." WHERE id = ".$db->sanitize($_GET['pack']));
} else {
    $db->execute("UPDATE modpacks SET latest = null WHERE id = ".$db->sanitize($_GET['pack']));
}

echo json_encode($response);
exit();
