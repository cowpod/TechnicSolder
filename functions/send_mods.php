<?php
header('Content-Type: application/json');

session_start();
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die('{"status":"error","message":"Login session has expired"}');
}
if (substr($_SESSION['perms'],3,1)!=="1") {
    echo '{"status":"error","message":"Insufficient permission!"}';
    exit();
}
$config = require("config.php");

global $db;
require_once("db.php");

// fiels = name of input in index.php
$file_name = $_FILES["fiels"]["name"];
$file_tmp = $_FILES["fiels"]["tmp_name"];

if (empty($file_tmp)) {
    error_log ('{"status":"error","message":"File is too big! Check your post_max_size (current value '.ini_get('post_max_size').') and upload_max_filesize (current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}');
    die ('{"status":"error","message":"File is too big! Check your post_max_size (current value '.ini_get('post_max_size').') and upload_max_filesize (current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}');
}

if (str_ends_with($file_name, '.jar')) {
    // check file magic
    // JAR files can be either java-archive or zip.
    $filetype=mime_content_type($file_tmp);
    if ($filetype!='application/java-archive'&&$filetype!='application/zip') {
        error_log ('{"status":"error","message":"Not a JAR file."}');
        die ('{"status":"error","message":"Not a JAR file."}');
    }
} else {
    error_log ('{"status":"error","message":"Not a JAR file."}');
    die ('{"status":"error","message":"Not a JAR file."}');
}

require('slugify.php');
require('compareZipContents.php');
require('modInfo.php');

if (!file_exists($file_tmp)){
    error_log ('{"status":"error","message":"Uploaded file does not exist!?"}');
    die ('{"status":"error","message":"Uploaded file does not exist!?"}');
}

if (!isset($db)){
    $db=new Db;
    $db->connect();
}

function processFile(string $filePath, string $fileName, array $modinfo): int {
    // todo: it is possible to craft a 'bad' modinfo data entry. mitigate it?

    error_log("processFile()");
    if (!is_dir('../mods/tmp')) {
        mkdir('../mods/tmp',0755,TRUE);
    }

    global $db;
    global $config;

    $slugified_mcv=!empty($modinfo['mcversion']) ? slugify2($modinfo['mcversion']) : '';

    $mod_zip_name = $modinfo['modid'].'-'.$slugified_mcv.'-'.$modinfo['version'].'.zip';
    $mod_zip_path_tmp ='../mods/tmp/'.$mod_zip_name;
    $mod_zip_path ='../mods/'.$mod_zip_name;
    $zip = new ZipArchive();

    if ($zip->open($mod_zip_path_tmp, ZipArchive::CREATE|ZipArchive::OVERWRITE)===TRUE) {
        $zip->addEmptyDir('mods');
        $zip->setCompressionName('mods', ZipArchive::CM_STORE); 

        if ($zip->addFile($filePath, 'mods/'.$fileName)) { 
            $zip->setCompressionName($filePath, ZipArchive::CM_STORE); 
            $zip->close();

            // if a file exists with same name, look for a file with duplicate contents OR rename it

            // if we got a file with the same name and is DIFFERENT
            if (file_exists($mod_zip_path) && compareZipContents($mod_zip_path, $mod_zip_path_tmp)===FALSE) {
                $counter=1;
                $new_dest_name = str_replace('.zip', '-'.$counter.'.zip', $mod_zip_path);
                // if our new name also exists and is DIFFERENT
                while(file_exists($new_dest_name) && compareZipContents($new_dest_name, $mod_zip_path_tmp)!==FALSE) {
                    $counter+=1;
                    $new_dest_name = str_replace('.zip', '-'.$counter.'.zip', $mod_zip_path);
                }
                $mod_zip_path = $new_dest_name;
             }

            // move tmp zip to actual zip, overwriting if identical
            rename($mod_zip_path_tmp, $mod_zip_path);

            $mod_zip_md5 = md5_file($mod_zip_path);

            $mcvrange = parse_interval_range($modinfo['mcversion']);

            $addq = $db->execute("INSERT INTO `mods` (`name`,`pretty_name`,`md5`,`url`,`link`,`author`,`description`,`version`,`mcversion`,`filename`,`type`,`loadertype`) VALUES ("
                ."'".$db->sanitize($modinfo['modid'])."',"
                ."'".$db->sanitize($modinfo['name'])."',"
                ."'".$db->sanitize($mod_zip_md5)."',"
                ."'"."',"
                ."'".$db->sanitize($modinfo['url'])."',"
                ."'".$db->sanitize($modinfo['authors'])."',"
                ."'".$db->sanitize($modinfo['description'])."',"
                ."'".$db->sanitize($modinfo['version'])."',"
                ."'".$db->sanitize($modinfo['mcversion'])."',"
                ."'".$db->sanitize(basename($mod_zip_path))."',"
                ."'mod',"
                ."'".$db->sanitize($modinfo['loadertype'])."'"
                .")");

            if ($addq) {
                assert(!empty($db->insert_id()));
                return $db->insert_id();
            } else {
                return -1;
            }
        } else {
            error_log ('{"status":"error","message":"Could not create zip. Out of disk space?"}');
            die ('{"status":"error","message":"Could not create zip. Out of disk space?"}');
        }
    } else {
        error_log ('{"status":"error","message":"Could not create zip. Bad folder permissions?"}');
        die ('{"status":"error","message":"Could not create zip. Bad folder permissions?"}');
    }
}

$mi = new modInfo();
$modinfo = $mi->getModInfo($file_tmp, $file_name);

// we only care about the first mod.
// todo: in delete modv, add a check to see if the file is used by another mod entry.
// ie. same file for both forge and fabric loader types!
$added_ids=[];
$added_modids=[];
// foreach ($modInfos as $modinfo) {
    if (empty($modinfo)) {
        error_log('{"status":"error","message":"Unable to add mod to database (empty).", "name": "'.$file_name.'"}');
        die('{"status":"error","message":"Unable to add mod to database (empty).", "name": "'.$file_name.'"}');
        // continue;
    }
    // if we have another mod of same version, name, mcversion, type, loadertype
    $query_mod_exists = $db->query("SELECT 1 FROM mods WHERE version = '".$db->sanitize($modinfo['version'])."' AND name = '".$db->sanitize($modinfo['modid'])."' AND mcversion = '".$db->sanitize($modinfo['mcversion'])."' AND `type` = 'mod' AND `loadertype` = '".$db->sanitize($modinfo['loadertype'])."' LIMIT 1");
    if ($query_mod_exists && sizeof($query_mod_exists)>0) {
        error_log('{"status": "error","message":"Mod already in database!"}');
        die('{"status": "error","message":"Mod already in database!"}');
    }
    // consequence: we allow multiple loadertypes (ie fabric, forge) of the exact same mod
    $result_mod_id = processFile($file_tmp, $file_name, $modinfo);
    if ($result_mod_id === -1) {
        error_log('{"status":"error","message":"Unable to add mod to database (-1).", "name": "'.$file_name.'"}');
        die('{"status":"error","message":"Unable to add mod to database (-1).", "name": "'.$file_name.'"}');
    } else {
        error_log('Successfully added modid='.$modinfo['modid'].' id='.$result_mod_id.' to database!');
        array_push($added_modids, $modinfo['modid']);
        array_push($added_ids, $result_mod_id);
    }
// }

$db->disconnect();

// error_log(json_encode($added_ids));

if (sizeof($added_ids)==0) {
    error_log('{"status":"error","message":"Unable to add mod to database.", "name": "'.$file_name.'"}');
    die('{"status":"error","message":"Unable to add mod to database.", "name": "'.$file_name.'"}');
}

// if we got warnings
$warn = $mi->getWarnings();
if (!empty($warn) && sizeof($warn)>0) {
    // accumulate them all into one, as we dont support displaying multiple seperate ones in index.php.
    $acc_message='';
    foreach ($warn as $w) {
        $acc_message.=$w.' ';
    }
    echo '{"status":"warn","message":"'.$acc_message.'Issue(s) must be addressed before using this mod."}';
} else {
    // get the last one added. we don't support displaying multiple..
    // todo: support displaying multiple in index.php
    // modid appears to be the id in the database, name appears to be modid...
    error_log('{"status":"succ","message":"Mod added.","modid":'.$added_ids[sizeof($added_ids)-1].',"name":"'.$added_modids[sizeof($added_modids)-1].'"}');
    echo '{"status":"succ","message":"Mod added.","modid":'.$added_ids[sizeof($added_ids)-1].',"name":"'.$added_modids[sizeof($added_modids)-1].'"}';
}

exit();
?>
