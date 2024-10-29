<?php
session_start();
$config = require("./config.php");

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}

require_once("./db.php");
$db=new Db;
$db->connect();

echo "<hr/>Altering table mods<br/>";

// 1.4.0: mod version ranges
// naturally, we assume user is using mysql.
$addtype1=$db->execute("ALTER TABLE `mods` ADD COLUMN `loadertype` VARCHAR(32);");
$addtype2=$db->execute("ALTER TABLE `builds` ADD COLUMN `loadertype` VARCHAR(32);");

if (!$addtype||!$addtype) {
    die("Couldn't add new columns loadertype to table `mods`! Are we already upgraded? <a href='/'>Click here to return to index</a>.");
}

mkdir('../upgrade_work');

echo "<hr/>Updating mod entries<br/>";

require('modInfo.php');
$mi = new modInfo();

$mods=$db->query("SELECT id,filename FROM `mods` WHERE type='mod'");

foreach ($mods as $mod) {
    $zip = new ZipArchive;
    if ($zip->open('../mods/'.$mod['filename']) === TRUE) {
        for ($i=0; $i<$zip->numFiles; $i++) {
            // ignore mods/ folder, get files within it
            if ($zip->statIndex($i)['name']=='mods/') 
                continue;

            if (str_starts_with($zip->statIndex($i)['name'], 'mods/')) {
                $filename = $zip->getNameIndex($i);

                if (pathinfo($filename, PATHINFO_EXTENSION) !== 'jar')
                    continue;

                $zip->extractTo('../upgrade_work');
                $jarfilename = '../upgrade_work/'.$filename;

                if (!file_exists($jarfilename)) {
                    echo 'failed to extract; extracted file does not exist!<br/>';
                    continue;
                }
                $mod_info = $mi->getModInfo($jarfilename, basename($jarfilename));
                $loader_type = $mod_info['loadertype'];


                $modltq = $db->query("SELECT loadertype from mods where id = ".$mod['id']);
                if ($modltq) {
                    if (sizeof($modltq)==1 && $modltq[0]['loadertype']===$loader_type) {
                        echo 'mod id='.$mod['id'].' name='.$mod['name'].' already has loadertype='.$loader_type.'. skipping.<br/>';
                        continue;
                    }
                }

                $updatemodx = $db->execute("UPDATE mods SET loadertype = '".$loader_type."' where id = ".$mod['id']);

                if ($updatemodx) {
                    echo 'mod id='.$mod['id'].', name='.$mod['name'].' updated, loadertype='.$loader_type.".<br/>";
                } else {
                    echo 'failed to update mod id='.$mod['id'].' name='.$mod['name'];
                }

                unlink($jarfilename);
                break;
            }
        }
    }
    $zip->close();
}

echo "<hr/>Updating modloader entries<br/>";

$forges=$db->query("SELECT id,filename FROM `mods` WHERE type='forge'");
foreach ($forges as $forge) {
    if (str_starts_with($forge['filename'], 'forge-')) {
        $db->execute("UPDATE mods SET loadertype='forge' WHERE id='".$forge['id']."'");
    }
    else if (str_starts_with($forge['filename'], 'neoforge-')) {
        $db->execute("UPDATE mods SET loadertype='neoforge' WHERE id='".$forge['id']."'");
    }
    else if (str_starts_with($forge['filename'], 'fabric-')) {
        $db->execute("UPDATE mods SET loadertype='fabric' WHERE id='".$forge['id']."'");
    }
}

echo "<hr/>Upgrade complete. <a href='/'>Click here to return to index</a>.</br>";

rmdir('../upgrade_work/mods');
rmdir('../upgrade_work');

$db->disconnect();
exit();

