<?php
session_start();
$config = require("./config.php");

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}

require_once("./db.php");
$db=new Db;
$db->connect();

// 1.4.0: mod version ranges
// naturally, we assume user is using mysql.
$addhigh=$db->execute("ALTER TABLE `mods` ADD COLUMN `loadertype` VARCHAR(128);");

if (!$addlow||!$addhigh) {
    die("Couldn't add new columns loadertype to table `mods`! Are we already upgraded? <a href='/'>Click here to return to index</a>.");
}

echo "Upgrading database...<br/>";

$mods=$db->query("SELECT id,name,pretty_name,url,link,author,donlink,description,version,md5,mcversion,filename,type FROM `mods` WHERE type='mod'");

// todo: automatically add loadertype for each mod in library

echo "Upgrade complete. <a href='/'>Click here to return to index</a>.";

$db->disconnect();
exit();

