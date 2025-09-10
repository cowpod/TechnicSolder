<?php
/*
TODO: This is insecure.
*/
define('CONFIG_VERSION', 1);

function get_config() {
    global $config;
    if (file_exists("./config.php")) {
        $config = require("./config.php");
    } else {
        die("We're not configured, or we're not on 1.3.5.");
    }

    if (!empty($config['config_version']) && $config['config_version']==CONFIG_VERSION) {
        $index = isset($config['dir']) ? $config['dir'] : '.';
        die("We appear to not be on 1.3.5.");
    }
}

function connect_db_and_set_db_in_config() {
    global $config;
    global $db;
    
    // write db-type to config immediately
    $config['db-type']='mysql';

    // test db
    require_once("./db.php");
    $db=new Db;
    $dbres = $db->connect();

    // write back old config since it didn't work.
    if (!$dbres) {
        die('DB configuration error. Please check that config.php has all the necessary database values: db-type,db-host,db-name,db-user,db-pass');
    }
}

function alter_db() {
    global $config;
    global $db;

    $addmetrics = $db->execute("CREATE TABLE metrics (
        name VARCHAR(128) PRIMARY KEY,
        time_stamp BIGINT,
        info TEXT
    ");
    if (!$addmetrics) {
        echo "Couldn't add new table metrics!<br/>";
    }

    // add mod jar md5
    $addmods_jarmd5 = $db->execute("ALTER TABLE mods ADD COLUMN jar_md5 VARCHAR(32);");
    if (!$addmods_jarmd5) {
        echo "Couldn't add new column jar_md5 to table mods!<br/>";
    }

    // per-user settings
    $addsettings = $db->execute("ALTER TABLE users ADD COLUMN settings LONGTEXT;");
    $addapikey = $db->execute("ALTER TABLE users ADD COLUMN api_key VARCHAR(128);");
    $addpriv = $db->execute("ALTER TABLE users ADD COLUMN privileged BOOLEAN;");
    if (!$addsettings || !$addapikey || !$addpriv) {
        echo "Couldn't add new column settings/api_key/privileged to table users!<br/>";
    }

    // 1.4.0: mod version ranges
    // naturally, we assume user is using mysql.
    $addtype=$db->execute("ALTER TABLE mods ADD COLUMN loadertype VARCHAR(32);");
    $addsize=$db->execute("ALTER TABLE mods ADD COLUMN filesize INTEGER;");
    if (!$addtype||!$addsize) {
        echo "Couldn't add new columns loadertype,filesize to table mods!<br/>";
    }

    $addtype=$db->execute("ALTER TABLE builds ADD COLUMN loadertype VARCHAR(32);");
    if (!$addtype) {
        echo "Couldn't add new columns loadertype to table builds!<br/>";
    }

    // increase size of mods column in builds table
    $longtext = $db->execute("ALTER TABLE builds MODIFY COLUMN mods LONGTEXT;");
    if ($longtext) {
        echo "Couldn't alter column mods for table builds!<br/>";
    }
}

function migrate_admin_user_from_config_to_db() {
    global $config;
    global $db;

    $icon = "iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAB9ElEQVR4Xu2bSytEcRiHZyJRaDYWRhJilFlYKjakNOWS7OxEGCRGpAg1KykRSlHSKLkO0YyFhSiRIQmbIcVEsnCXW/EJPB/g9Jvt0/8s3t73+b3nnDnmpZWaXxP8dssRm6yL+XTc9OO1Ib+9GWCe60BuyUpEvvDYiNysAqgDNAJygCSoFPi/AoaPwbCvXnRAKKoZc/T7rA/5kasEeV1wEvlJnBf5lM+KfD16mPcAFUAdoBGQA8gSkqBSwOAxmBZ8QQdsOTIwRzsPOae7Iy/w/Op3DvLwZd4zgrYnPJ83Xcp7gAqgDtAIyAFkCUlQKWDwGKzdPeUH//ftmKPz9ePIQ6m1yANufq+QPteK58s6tpHvRZTxHqACqAM0AnIAWkISVAoYOwaf13bQAZn2WSzAQ1EB38/3FyP/9R0jz/K/I/cMxSM3VSTzHqACqAM0AnIAWUISVAoYPAbfe6/RAV07b5ijH/uFyD8Dd8jnejy8R+TwnuG8GsTzpXdJvAeoAOoAjYAcQJaQBJUCBo9B+6sDHfDSUoM5Wm1uQ34Z60YeMzOB3DJygNy5yU+sHGNNvAeoAOoAjYAcQJaQBJUCBo/B7Cr+aMrvnMEctVbx9wCVXbxINboS8Pqu0DnyFDf//2B0o4H3ABVAHaARwD1ADpAElQKGjsE/aSRgFj7BEuwAAAAASUVORK5CYII=";
    $name = $config['author'];
    $email= $config['mail'];
    $pass = $config['pass'];
    $perms = "1111111";
    $api_key = $config['api_key'];

    // hash password
    if (empty($config['encrypted']||!$config['encrypted'])){
        $pass = password_hash($pass, PASSWORD_DEFAULT);
    }

    $db->execute("INSERT INTO users (name,display_name,pass,perms,privileged,icon,api_key) array_values(
        '{$email}',
        '{$name}',
        '{$pass}',
        '{$perms}',
        TRUE,
        '{$icon}',
        '{$api_key}'
    );");

    unset($config['author']);
    unset($config['mail']);
    unset($config['pass']);
    unset($config['encrypted']);
}

function rrmdir($dir) { 
   if (is_dir($dir)) { 
     $objects = scandir($dir);
     foreach ($objects as $object) { 
       if ($object != "." && $object != "..") { 
         if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
           rrmdir($dir. DIRECTORY_SEPARATOR .$object);
         else
           unlink($dir. DIRECTORY_SEPARATOR .$object); 
       } 
     }
     rmdir($dir); 
   } 
}

function update_mod_entries() {
    global $config;
    global $db;

    mkdir('../upgrade_work');

    require('modInfo.php');
    $mi = new modInfo();

    $mods=$db->query("SELECT * FROM mods WHERE type='mod'");

    foreach ($mods as $mod) {
        $zip = new ZipArchive;
        if ($zip->open("../mods/{$mod['filename']}") === TRUE) {
            for ($i=0; $i<$zip->numFiles; $i++) {
                // ignore mods/ folder, get files within it
                if ($zip->statIndex($i)['name']=='mods/') 
                    continue;

                if (str_starts_with($zip->statIndex($i)['name'], 'mods/')) {
                    $filename = $zip->getNameIndex($i);

                    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'jar')
                        continue;

                    $zip->extractTo('../upgrade_work');
                    $jarfilename = "../upgrade_work/{$filename}";

                    if (!file_exists($jarfilename)) {
                        echo 'failed to extract; extracted file does not exist!<br/>';
                        continue;
                    }

                    $jar_md5 = md5_file($jarfilename);
                    
                    $update_jar_md5 = $db->execute("UPDATE mods SET jar_md5 = '{$jar_md5}' WHERE id = {$mod['id']} AND jar_md5 <> '{$jar_md5}'");

                    if ($update_jar_md5) {
                        echo "Updated mod id={$mod['id']} name='{$mod['name']}', jar_md5='{$jar_md5}'<br/>";
                    } else {
                        echo "Failed to update mod id={$mod['id']} name='{$mod['name']}' jar_md5.<br/>";
                    }

                    $modltq = $db->query("SELECT loadertype FROM mods WHERE id = {$mod['id']}");
                    if ($modltq) {
                        if (!empty($modltq[0]['loadertype'])) {
                            echo "Skipping mod id={$mod['id']} name='{$mod['name']}' already has loadertype='{$modltq[0]['loadertype']}'<br/>";
                            continue;
                        }
                    }

                    $mod_info = $mi->getModInfo($jarfilename, basename($jarfilename));

                    $did_set_lt = null;
                    foreach ($mod_info as $loader_type => $info) {
                        if ($info !== null) {
                            if ($did_set_lt === null) {
                                $updatemodx = $db->execute("UPDATE mods SET loadertype = '{$loader_type}' WHERE id = {$mod['id']}");
                                if ($updatemodx) {
                                    echo "Updated mod id={$mod['id']} name='{$mod['name']}', loadertype='{$loader_type}'<br/>";
                                    $did_set_lt = $loader_type;
                                } else {
                                    echo "Failed to update mod id={$mod['id']} name='{$mod['name']}', loader_type.<br/>";
                                }
                            } else { // we got a multi-loader mod!
                                $mod2 = $mod;
                                unset($mod2['id']);
                                $cols = implode(',', array_keys($mod2));
                                $vals = implode(',', array_values($mod2));

                                $addmod2x = $db->execute("INSERT INTO mods ({$cols}) VALUES ({$vals})");
                                if (!$addmod2x) {
                                    die("could not add new mod to db<br/>");
                                }

                                $new_id = $db->insert_id();
                                echo "Added multi-loader mod for id={$mod['id']}=>{$new_id} name='{$mod['name']}, loadertype='{$did_set_lt}'=>'{$loader_type}'<br/>";
                            }
                        }
                    }

                    unlink($jarfilename);
                    break;
                }
            }
        }
        $zip->close();
    }

    rrmdir('../upgrade_work');
}

function update_modloader_entries() {
    global $config;
    global $db;

    $forges=$db->query("SELECT id,filename,loadertype FROM mods WHERE type='forge'");
    foreach ($forges as $forge) {
        if (empty($forge['loadertype'])) {
            if (str_starts_with($forge['filename'], 'forge-')) {
                $db->execute("UPDATE mods SET loadertype='forge' WHERE id={$forge['id']}");
            }
            else if (str_starts_with($forge['filename'], 'neoforge-')) {
                $db->execute("UPDATE mods SET loadertype='neoforge' WHERE id={$forge['id']}");
            }
            else if (str_starts_with($forge['filename'], 'fabric-')) {
                $db->execute("UPDATE mods SET loadertype='fabric' WHERE id={$forge['id']}");
            } else {
                error_log("Unknown filename {$forge['filename']}");
                echo "Unknown filename {$forge['filename']}";
                continue; // idk what this is
            }
            echo "Updated '{$forge['filename']}'<br/>";
        } else {
            echo "Skipping '{$forge['filename']}' as it already has loadertype='{$forge['loadertype']}'<br/>";
        }
    }
}

function update_build_entries() {
    global $config;
    global $db;

    $builds=$db->query("SELECT id,name,mods,loadertype FROM builds");

    foreach ($builds as $build) {
        if (empty($build['loadertype'])) {
            // now get the mod details
            $mod_loadertype='';
            foreach (explode(",", $build['mods']) as $id) {
                $mod_loadertype = $db->query("SELECT id,loadertype FROM mods WHERE id = {$id} AND type='forge'");
                if ($mod_loadertype===FALSE || empty($mod_loadertype) || sizeof($mod_loadertype)!==1) {
                    continue;
                }
                // at this point we have a forge entry
                $mod_loadertype=$mod_loadertype[0]['loadertype'];
                break;
            }
            if (!empty($mod_loadertype)) {
                $updateres = $db->execute("UPDATE builds SET loadertype = '{$mod_loadertype}' WHERE id = {$build['id']}");

                if ($updateres===TRUE) {
                    echo "Updated '{$build['name']}' = '{$mod_loadertype}'<br/>";
                } else {
                    echo "Failed to update '{$build['name']}'<br/>";
                }
            } else {
                echo "Failed to update '{$build['name']}'<br/>";
            }
        } else {
            echo "Build '{$build['name']}' already has loadertype='{$build['loadertype']}'<br/>";
        }
    }
}

function update_modpack_rec_latest() {
    global $config;
    global $db;

    // change latest,recommended to ids instead of names. allows renaming without breaking
    $modpacks = $db->query("SELECT id,name,latest,recommended from modpacks");
    foreach ($modpacks as $modpack) {
        // get latest public build
        $lpq = $db->query("SELECT id,name FROM builds WHERE public = 1 AND modpack = {$modpack['id']} AND name= '{$modpack['latest']}' ORDER BY id DESC LIMIT 1");
        if ($lpq && sizeof($lpq)==1) {
            $db->execute("UPDATE modpacks SET latest = {$lpq[0]['id']} WHERE id = {$modpack['id']}");
            echo "{$modpack['name']}: latest={$lpq[0]['name']} ({$lpq[0]['id']})<br/>";
        }
        // get recommended public build
        $lpq = $db->query("SELECT id FROM builds WHERE public = 1 AND modpack = {$modpack['id']} AND name= '{$modpack['recommended']}' ORDER BY id DESC LIMIT 1");
        if ($lpq && sizeof($lpq)==1) {
            $db->execute("UPDATE modpacks SET recommended = {$lpq[0]['id']} WHERE id = {$modpack['id']}");
            echo "{$modpack['name']}: recommended={$lpq[0]['name']} ({$lpq[0]['id']})<br/>";
        }
    }
}

$config = null;
$db = null;

get_config();
connect_db_and_set_db_in_config();

// require("./configuration.php");
// $config = new Config();
// require("./db.php");
// $db = new Db();
// if ($db->connect()===FALSE) {
//     die("Couldn't connect to database!");
// }

alter_db();
migrate_admin_user_from_config_to_db();
update_mod_entries();
update_modloader_entries();
update_build_entries();
update_modpack_rec_latest();

$db->disconnect();

$config['config_version']=CONFIG_VERSION;
// save to new config
require_once('./configuration.php');
$config_new=new Config();
if ($config_new->setall($config)) {
    unlink('config.php');
}

exit();

