<?php
header('Content-Type: application/json');
session_start();

if (empty($_GET['id'])) {
    die("Build not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'],2,1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

require("db.php");
$db=new Db;
$db->connect();

$bq = $db->query("SELECT * FROM `builds` WHERE `id` = ".$db->sanitize($_GET['id']));
$build = ($bq);
$db->query("UPDATE `modpacks` SET `recommended` = '".$build['name']."' WHERE `id` = ".$build['modpack']);
$response = array(
    "name" => $build['name'],
    "mc" => $build['minecraft']
);

$db->disconnect();

echo(json_encode($response));
exit();
