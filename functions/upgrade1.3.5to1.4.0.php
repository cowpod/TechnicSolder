<?php
define('CONFIG_VERSION', 1);
session_start();
require_once('./config.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

if ($config->exists('config_version') && $config->get('config_version')==CONFIG_VERSION) {
    $index = $config->exists('dir') ? $config->get('dir') : '.';
    die("<h2>This script is only meant to be run when upgrading a 1.3.5 install to 1.4.0.</h2><a href='{$index}'>return to index</a>");
}

echo "<hr/>Adding db-type to config<br/>";
$config_old = $config;

// write db-type to config immediately
$config->set('db-type','mysql');
file_put_contents("./config.php", '<?php return ('.var_export($config,true).') ?>');

// test db
require_once("./db.php");
$db=new Db;
$dbres = $db->connect();

// write back old config since it didn't work.
if (!$dbres) {
    $config->setall($config_old);
    die('DB configuration error. Please check that config.php has all the necessary database values: db-type,db-host,db-name,db-user,db-pass');
}

echo "<hr/>Adding table row<br/>";

$addmetrics = $db->execute("CREATE TABLE metrics (
    name VARCHAR(128) PRIMARY KEY,
    time_stamp BIGINT,
    info TEXT
");
if (!$addmetrics) {
    die("Couldn't add new table metrics! Are we already upgraded? <a href='/'>Click here to return to index</a>.");
}

echo "<hr/>Altering table columns<br/>";

// per-user settings
$addsettings = $db->execute("ALTER TABLE users ADD COLUMN settings LONGTEXT;");
$addapikey = $db->execute("ALTER TABLE users ADD COLUMN api_key VARCHAR(128);");
$addpriv = $db->execute("ALTER TABLE users ADD COLUMN privileged BOOLEAN;");
if (!$addsettings || !$addapikey || !$addpriv) {
    die("Couldn't add new column settings/api_key/privileged to table users! Are we already upgraded? <a href='/'>Click here to return to index</a>.");
}

// 1.4.0: mod version ranges
// naturally, we assume user is using mysql.
$addtype=$db->execute("ALTER TABLE mods ADD COLUMN loadertype VARCHAR(32);");
$addsize=$db->execute("ALTER TABLE mods ADD COLUMN filesize INTEGER;");
if (!$addtype||!$addsize) {
    die("Couldn't add new columns loadertype,filesize to table mods! Are we already upgraded? <a href='/'>Click here to return to index</a>.");
}

$addtype=$db->execute("ALTER TABLE builds ADD COLUMN loadertype VARCHAR(32);");
if (!$addtype) {
    die("Couldn't add new columns loadertype to table builds! Are we already upgraded? <a href='/'>Click here to return to index</a>.");
}

echo "<hr/>Migrating admin user<br/>";

$icon = "iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAB9ElEQVR4Xu2bSytEcRiHZyJRaDYWRhJilFlYKjakNOWS7OxEGCRGpAg1KykRSlHSKLkO0YyFhSiRIQmbIcVEsnCXW/EJPB/g9Jvt0/8s3t73+b3nnDnmpZWaXxP8dssRm6yL+XTc9OO1Ib+9GWCe60BuyUpEvvDYiNysAqgDNAJygCSoFPi/AoaPwbCvXnRAKKoZc/T7rA/5kasEeV1wEvlJnBf5lM+KfD16mPcAFUAdoBGQA8gSkqBSwOAxmBZ8QQdsOTIwRzsPOae7Iy/w/Op3DvLwZd4zgrYnPJ83Xcp7gAqgDtAIyAFkCUlQKWDwGKzdPeUH//ftmKPz9ePIQ6m1yANufq+QPteK58s6tpHvRZTxHqACqAM0AnIAWkISVAoYOwaf13bQAZn2WSzAQ1EB38/3FyP/9R0jz/K/I/cMxSM3VSTzHqACqAM0AnIAWUISVAoYPAbfe6/RAV07b5ijH/uFyD8Dd8jnejy8R+TwnuG8GsTzpXdJvAeoAOoAjYAcQJaQBJUCBo9B+6sDHfDSUoM5Wm1uQ34Z60YeMzOB3DJygNy5yU+sHGNNvAeoAOoAjYAcQJaQBJUCBo/B7Cr+aMrvnMEctVbx9wCVXbxINboS8Pqu0DnyFDf//2B0o4H3ABVAHaARwD1ADpAElQKGjsE/aSRgFj7BEuwAAAAASUVORK5CYII=";
$name = $config->get('author');
$email= $config->get('mail');
$pass = $config->get('pass');
$perms = "1111111";
$api_key = $config->get('api_key');

// hash password
if (empty($config->get('encrypted')||!$config->get('encrypted'))){
    $pass = password_hash($pass, PASSWORD_DEFAULT);
}

$db->execute("INSERT INTO users (name,display_name,pass,perms,privileged,icon,api_key) array_values(
    '".$email."',
    '".$name."',
    '".$pass."',
    '".$perms."',
    TRUE,
    '".$icon."',
    '".$api_key."'
);");

unset($config->get('author'));
unset($config->get('mail'));
unset($config->get('pass'));
unset($config->get('encrypted'));

echo "<hr/>Updating mod entries<br/>";

mkdir('../upgrade_work');

require('modInfo.php');
$mi = new modInfo();

$mods=$db->query("SELECT id,filename FROM mods WHERE type='mod'");

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
                        echo 'Skipping mod id='.$mod['id'].' name='.$mod['name'].' already has loadertype='.$loader_type.'<br/>';
                        continue;
                    }
                }

                $updatemodx = $db->execute("UPDATE mods SET loadertype = '".$loader_type."' where id = ".$mod['id']);

                if ($updatemodx) {
                    echo 'Updated mod id='.$mod['id'].', name='.$mod['name'].', loadertype='.$loader_type.".<br/>";
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

$forges=$db->query("SELECT id,filename,loadertype FROM mods WHERE type='forge'");
foreach ($forges as $forge) {
    if (empty($forge['loadertype'])) {
        if (str_starts_with($forge['filename'], 'forge-')) {
            $db->execute("UPDATE mods SET loadertype='forge' WHERE id='".$forge['id']."'");
        }
        else if (str_starts_with($forge['filename'], 'neoforge-')) {
            $db->execute("UPDATE mods SET loadertype='neoforge' WHERE id='".$forge['id']."'");
        }
        else if (str_starts_with($forge['filename'], 'fabric-')) {
            $db->execute("UPDATE mods SET loadertype='fabric' WHERE id='".$forge['id']."'");
        } else {
            error_log("unknown filename ".$forge['filename']);
            echo "Unknown filename ".$forge['filename'];
            continue; // idk what this is
        }
        echo "Updated ".$forge['filename']."<br/>";
    } else {
        echo "Skipping ".$forge['filename']." as it already has loadertype=".$forge['loadertype']."<br/>";
    }
}

echo "<hr/>Updating builds entries<br/>";

$builds=$db->query("SELECT id,name,mods,loadertype FROM builds");

foreach ($builds as $build) {
    if (empty($build['loadertype'])) {
        // now get the mod details
        $mod_loadertype="";
        foreach (explode(",", $build['mods']) as $id) {
            $mod_loadertype = $db->query("SELECT id,loadertype FROM mods WHERE id = ".$id." AND type='forge'");
            if ($mod_loadertype===FALSE || empty($mod_loadertype) || sizeof($mod_loadertype)!==1) {
                continue;
            }
            // at this point we have a forge entry
            $mod_loadertype=$mod_loadertype[0]['loadertype'];
            break;
        }
        if (!empty($mod_loadertype)) {
            $updateres = $db->execute("UPDATE builds SET loadertype = '".$mod_loadertype."' WHERE id = ".$build['id']);

            if ($updateres===TRUE) {
                echo "Updated ".$build['name']." = '".$mod_loadertype."'<br/>";
            } else {
                echo "Failed to update ".$build['name']."<br/>";
            }
        } else {
            echo "Failed to update ".$build['name']."<br/>";
        }
    } else {
        echo "Build ".$build['name']." already has loadertype=".$build['loadertype']."<br/>";
    }
}

echo "<hr/>Updating modpack recommended,latest<br/>";

// change latest,recommended to ids instead of names. allows renaming without breaking
$modpacks = $db->query("SELECT id,name,latest,recommended from modpacks");
foreach ($modpacks as $modpack) {
    // get latest public build
    $lpq = $db->query("SELECT id,name FROM builds WHERE public = 1 AND modpack = ".$modpack['id']." AND name= '".$modpack['latest']."' ORDER BY id DESC LIMIT 1");
    if ($lpq && sizeof($lpq)==1) {
        $db->execute("UPDATE modpacks SET latest = ".$lpq[0]['id']." WHERE id = ".$modpack['id']);
        echo $modpack['name'].": latest=".$lpq[0]['name']." (".$lpq[0]['id'].")<br/>";
    }
    // get recommended public build
    $lpq = $db->query("SELECT id FROM builds WHERE public = 1 AND modpack = ".$modpack['id']." AND name= '".$modpack['recommended']."' ORDER BY id DESC LIMIT 1");
    if ($lpq && sizeof($lpq)==1) {
        $db->execute("UPDATE modpacks SET recommended = ".$lpq[0]['id']." WHERE id = ".$modpack['id']);
        echo $modpack['name'].": recommended=".$lpq[0]['name']." (".$lpq[0]['id'].")<br/>";
    }
}

echo "<hr/>Upgrade complete. <a href='/'>Click here to return to index</a>.</br>";

rmdir('../upgrade_work/mods');
rmdir('../upgrade_work');

$db->disconnect();


$config->get('config_version',CONFIG_VERSION);
exit();

