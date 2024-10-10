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
$addlow=$db->execute("ALTER TABLE `mods` ADD COLUMN `mcversion_low` VARCHAR(128);");
$addhigh=$db->execute("ALTER TABLE `mods` ADD COLUMN `mcversion_high` VARCHAR(128);");
$addhigh=$db->execute("ALTER TABLE `mods` ADD COLUMN `loadertype` VARCHAR(128);");

if (!$addlow||!$addhigh) {
    die("Couldn't add new columns mcversion_low, mcversion_high to table `mods`! Are we already upgraded? <a href='/'>Click here to return to index</a>.");
}

echo "Upgrading database...<br/>";

$mods=$db->query("SELECT id,name,pretty_name,url,link,author,donlink,description,version,md5,mcversion,filename,type FROM `mods` WHERE type='mod'");

if ($mods && sizeof($mods)>0) {
    foreach ($mods as $mod) {
        echo "Updating mod id=".$mod['id'],", name=".$mod['name']."<br/>";

        $min=null;
        $max=null;
        $mcvrange=explode(",", trim($mod['mcversion'],"[]()"));

        if (sizeof($mcvrange)==2) {
            $min=$mcvrange[0];
            $max=$mcvrange[1];
        }
        elseif (sizeof($mcvrange)==1) {
            $min=$mcvrange[0];
        }
        else {
            echo "Ignoring mod with invalid mcversion string: ".$mod['mcversion']."<br/>";
            continue; // invalid mcversion string
        }

        echo "Got min=".$min.", max=".$max."<br/>";
        $db->execute("UPDATE mods SET mcversion_low='".$min."', mcversion_high='".$max."' WHERE id=".$mod['id']);
    }

    echo "Upgrade complete. <a href='/'>Click here to return to index</a>.";
}



$db->disconnect();

exit();
