<?php
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}
if (substr($_SESSION['perms'], 0, 1)!=="1") {
    die('{"status":"error","message":Insufficient permission!"}');
}
if (empty($_POST['id'])) {
    die('{"status":"error","message":"Modpack not specified."}');
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}
global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$ispublic = (isset($_POST['ispublic']) && $_POST['ispublic']=="on") ? 1 : 0;

$db->execute("UPDATE `modpacks`
    SET `name` = '".$db->sanitize($_POST['name'])."',
     `display_name` = '".$db->sanitize($_POST['display_name'])."',
      `public` = ".$ispublic."
      WHERE `id`=".$_POST['id']
);

die('{"status":"succ","message":"Modpack details updated."}');
