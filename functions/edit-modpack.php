<?php
session_start();
$config = require("./config.php");

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

$ispublic = 0;
if ($_GET['ispublic']=="on") {
    $ispublic=1;
}
$db->execute("UPDATE `modpacks`
    SET `name` = '".$db->sanitize($_GET['name'])."',
     `display_name` = '".$db->sanitize($_GET['display_name'])."',
      `public` = ".$ispublic."
      WHERE `id`=".$_GET['id']
);

header("Location: ".$config['dir']."modpack?id=".$_GET['id']);
