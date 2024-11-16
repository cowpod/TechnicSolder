<?php
session_start();
require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

if (empty($_GET['id'])) {
    die("Modpack not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 0, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$ispublic = (isset($_GET['ispublic']) && $_GET['ispublic']=="on") ? 1 : 0;

$db->execute("UPDATE `modpacks`
    SET `name` = '".$db->sanitize($_GET['name'])."',
     `display_name` = '".$db->sanitize($_GET['display_name'])."',
      `public` = ".$ispublic."
      WHERE `id`=".$_GET['id']
);

header("Location: ".$config->get('dir')."modpack?id=".$_GET['id']);
