<?php
session_start();
$config = require("./config.php");

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}

require_once("db.php");
$db=new Db;
$db->connect();

$db->execute("ALTER TABLE `builds` ADD COLUMN `public` TINYINT(1);");
$db->execute("ALTER TABLE `builds` ADD COLUMN `clients` LONGTEXT;");
$db->execute("ALTER TABLE `modpacks` ADD COLUMN `public` TINYINT(1);");
$db->execute("ALTER TABLE `modpacks` ADD COLUMN `clients` LONGTEXT;");
$db->execute("UPDATE `modpacks` SET `public` = 1;");
$db->execute("UPDATE `builds` SET `public` = 1;"); 
$db->execute("CREATE TABLE clients (id int(8) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(128), UUID VARCHAR(128), UNIQUE (UUID));";

$db->disconnect();
header("Location: ".$config['dir']."clients");
exit();
