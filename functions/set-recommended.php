<?php
header('Content-Type: application/json');
session_start();

if (empty($_GET['id'])) {
    die("Build ID not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'],2,1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

require_once("db.php");
$db=new Db;
$db->connect();

$bq = $db->query("SELECT * FROM `builds` WHERE `id` = ".$db->sanitize($_GET['id']));
if ($bq) {
    assert(sizeof($bq)==1);
    $build = $bq[0];
}

$db->execute("UPDATE `modpacks` SET `recommended` = '".$db->sanitize($_GET['id'])."' WHERE `id` = ".$build['modpack']);

$response = array(
    "name" => $build['name'],
    "mc" => $build['minecraft']
);

$db->disconnect();

echo(json_encode($response));
exit();
