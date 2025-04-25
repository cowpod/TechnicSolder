<?php
define('MAX_TMP_ROLLS',10);
header('Content-Type: application/json');

session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('sanitize.php');

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->mods_upload()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

require_once('slugify.php');

// fiels = name of input in index.php

// download from given _POST data
// todo: download directly to disk or buffer 
function download_url($url, $filename) {
    $apiversion_raw = @file_get_contents('../api/version.json');
    if ($apiversion_raw === false || empty($apiversion_raw)) {
        die('{"status":"error","message": "Could not get solder version"}');
    }
    $apiversion_json = @json_decode($apiversion_raw, true);
    if ($apiversion_json === null) {
        die('{"status":"error","message": "Could not decode api version data"}');
    }
    $apiversion = $apiversion_json['version'];

    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: TheGameSpider/TechnicSolder/{$apiversion} (Download to package mod into ZIP)\r\nContent-Type: application/json"
        ]
    ];

    $context = stream_context_create($options);
    $getfile = @file_get_contents($url, false, $context);
    if ($getfile === false || empty($getfile)) {
        die('{"status":"error","message":"Could not download file"}');
    }
    if (empty($filename)) {
        $file_name = bin2hex(random_bytes(16)).'.jar';
        error_log("filename empty! using random name: {$file_name}");
    } else {
        $file_name = slugify3($filename);
    }

    $file_tmp_dir = sys_get_temp_dir().'/send_mods/'.bin2hex(random_bytes(16));
    if (!is_dir($file_tmp_dir)) {
        mkdir($file_tmp_dir, recursive:true);
    }
    $file_tmp = $file_tmp_dir.'/'.$file_name;

    $num_rolls = 0;
    while (file_exists($file_tmp)) {
        if ($num_rolls>=MAX_TMP_ROLLS) {
            error_log('Could not upload file to temporary directory after '.MAX_TMP_ROLLS.' attempts. Temporary dir: '.$file_tmp_dir);
            die('Could not upload file to temporary directory after '.MAX_TMP_ROLLS.' attempts.');
        }
        $num_rolls+=1;
        error_log("send_mods.php: temp file exists, re-rolling ".$file_tmp);
        $file_tmp_dir = sys_get_temp_dir().'/send_mods/'.bin2hex(random_bytes(16));
        if (!is_dir($file_tmp_dir)) {
            mkdir($file_tmp_dir, recursive:false);
        }
        $file_tmp = $file_tmp_dir.'/'.$file_name;
    }

    error_log('got temp file: '.$file_tmp);

    $putfile = @file_put_contents($file_tmp, $getfile);
    if ($putfile === false) {
        die('{"status":"error","message":"Could not save file"}');
    } 
    
    // done getting file from url
    error_log('file downloaded to '.$file_tmp);

    return [$file_tmp, $file_name];
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

    if ($zip->open($mod_zip_path_tmp, ZipArchive::CREATE|ZipArchive::OVERWRITE) === TRUE) {
        $zip->addEmptyDir('mods');
        $zip->setCompressionName('mods', ZipArchive::CM_STORE); 

        if ($zip->addFile($filePath, 'mods/'.$fileName)) { 
            $zip->setCompressionName('mods/'.$fileName, ZipArchive::CM_STORE); 
            $zip->close();

            // if a file exists with same name, look for a file with duplicate contents OR rename it

            // if we got a file with the same name and is DIFFERENT
            if (file_exists($mod_zip_path) && compareZipContents($mod_zip_path, $mod_zip_path_tmp) === FALSE) {
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

            $jar_md5 = md5_file($filePath);
            $mod_zip_md5 = md5_file($mod_zip_path);
            $zip_size = filesize($mod_zip_path);

            // error_log('adding mod of loadertype='.$modinfo['loadertype']);
            $addq = $db->execute("INSERT INTO mods (name,pretty_name,md5,jar_md5,url,link,author,description,version,mcversion,filename,type,loadertype,filesize) VALUES (
                '{$db->sanitize($modinfo['modid'])}',
                '{$db->sanitize($modinfo['name'])}',
                '{$db->sanitize($mod_zip_md5)}',
                '{$db->sanitize($jar_md5)}',
                '',
                '{$db->sanitize($modinfo['url'])}',
                '{$db->sanitize($modinfo['authors'])}',
                '{$db->sanitize($modinfo['description'])}',
                '{$db->sanitize($modinfo['version'])}',
                '{$db->sanitize($modinfo['mcversion'])}',
                '{$db->sanitize(basename($mod_zip_path))}',
                'mod',
                '{$db->sanitize($modinfo['loadertype'])}',
                {$zip_size}
                );");

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

if (isset($_FILES['fiels']) && isset($_FILES["fiels"]["name"]) && isset($_FILES["fiels"]["tmp_name"])) {
    // get from uploaded file

    $file_name = $_FILES["fiels"]["name"];
    $file_tmp = $_FILES["fiels"]["tmp_name"];
} else {
    // get from URL

    if (!$config->exists('modrinth_integration') || $config->get('modrinth_integration') != 'on') {
        die('{"status":"error","message":"Modrinth integration is disabled - refusing URL"}');
    }

    if (empty($_POST['url']) || !filter_var($_POST['url'], FILTER_VALIDATE_URL)) {
        die('{"status":"error","message":"Invalid URL (not a JAR?)"}');
    }

    // todo: sanitize $_POST['filename']

    [$file_tmp, $file_name] = download_url($_POST['url'], $_POST['filename']);
}

if (empty($file_tmp)) {
    error_log ('{"status":"error","message":"File is too big! Check your post_max_size (current value '.ini_get('post_max_size').') and upload_max_filesize (current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}');
    die ('{"status":"error","message":"File is too big! Check your post_max_size (current value '.ini_get('post_max_size').') and upload_max_filesize (current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}');
}

if (str_ends_with($file_name, '.jar')) {
    // check file magic
    // JAR files can be either java-archive or zip.
    $filetype = mime_content_type($file_tmp);
    if ($filetype!='application/java-archive' && $filetype!='application/zip') {
        error_log ('{"status":"error","message":"Not a JAR file."}');
        die ('{"status":"error","message":"Not a JAR (or renamed ZIP) file."}');
    }
} else {
    error_log ('{"status":"error","message":"Not a JAR file."}');
    die ('{"status":"error","message":"Not a JAR file."}');
}

if (!file_exists($file_tmp)){
    error_log ('{"status":"error","message":"Uploaded file does not exist!?"}');
    die ('{"status":"error","message":"Uploaded file does not exist!?"}');
}

require_once('compareZipContents.php');
require_once('modInfo.php');

require_once("db.php");
$db = new Db;
$db->connect();

$mi = new modInfo();
$modinfos = $mi->getModInfo($file_tmp, $file_name);
$warn = $mi->getWarnings();

// if any mod entries are missing mcversion, use fallback from modloader
// note if we don't have a fallback (no modloaders installed),
// this will be empty.

$fallback_mcversion = '';
if (!empty($_POST['fallback_mcversion'])) {
    $fallback_mcversion = $db->sanitize($_POST['fallback_mcversion']);
}

foreach ($modinfos as $type=>$mod) {
    if (!empty($mod) && empty($mod['mcversion'])) {
        $modinfos[$type]['mcversion'] = $fallback_mcversion;
        unset($warn[array_search('Missing mcversion!', array_keys($warn))]);
    }
}

$added_ids=[];
$added_modids=[];
$added_authors=[];
$added_pretty_names=[];
$added_versions=[];
$added_mcversions=[];
$num_mods_to_add=sizeof($modinfos);

$num_already_added=0;
$num_process_failed=0;

foreach ($modinfos as $modinfo) {
    if (empty($modinfo)) {
        $num_mods_to_add-=1;
        error_log('{"status":"error","message":"Unable to add mod to database (empty)."}');
        // die('{"status":"error","message":"Unable to add mod to database (empty).", "name": "'.$file_name.'"}');
        continue;
    }
    // if we have another mod of same name, version, mcversion, type, loadertype
    $query_mod_exists = $db->query("SELECT id,name FROM mods 
        WHERE name = '{$db->sanitize($modinfo['modid'])}' 
        AND version = '{$db->sanitize($modinfo['version'])}' 
        AND mcversion = '{$db->sanitize($modinfo['mcversion'])}' 
        AND type = 'mod' 
        AND loadertype = '{$db->sanitize($modinfo['loadertype'])}'");

    if ($query_mod_exists && sizeof($query_mod_exists)>0) {
        error_log('{"status": "info","message":"Mod already in database!","modid":"'.$query_mod_exists[0]['id'].'","name":"'.$query_mod_exists[0]['name'].'"}');
        if ($num_mods_to_add==1) {
            die('{"status": "info","message":"Mod already in database!","modid":"'.$query_mod_exists[0]['id'].'","name":"'.$query_mod_exists[0]['name'].'"}');
        } else {
            array_push($warn, 'Mod already in database (#'.$num_mods_to_add.')');
            $num_already_added+=1;
            continue;
        }
    }
    // consequence: we allow multiple loadertypes (ie fabric, forge) of the exact same mod
    $result_mod_id = processFile($file_tmp, $file_name, $modinfo);
    if ($result_mod_id === -1) {
        error_log('{"status":"error","message":"Unable to add mod to database (-1).", "name": "'.$file_name.'"}');
        if ($num_mods_to_add==1) {
            die('{"status":"error","message":"Unable to add mod to database (-1).", "name": "'.$file_name.'"}');
        } else {
            array_push($warn, 'Unable to add mod to database -1 (#'.$num_mods_to_add.')');
            $num_process_failed+=1;
            continue;
        }
    } else {
        error_log('Successfully added modid='.$modinfo['modid'].' id='.$result_mod_id.' to database!');
        error_log(json_encode($modinfo));
        array_push($added_modids, $modinfo['modid']);
        array_push($added_ids, $result_mod_id);
        array_push($added_authors, $modinfo['authors']);
        array_push($added_pretty_names, $modinfo['name']);
        array_push($added_versions, $modinfo['version']);
        array_push($added_mcversions, $modinfo['mcversion']);
    }
}

$db->disconnect();

// error_log(json_encode($added_ids));

if (sizeof($added_ids)+$num_already_added+$num_process_failed==0) {
    error_log('{"status":"error","message":"Unable to add mod to database (parsed 0 mods)."}');
    die('{"status":"error","message":"Unable to add mod to database (parsed 0 mods)."}');
}

$moredata = '"modid":'.json_encode($added_ids,JSON_UNESCAPED_SLASHES).',"name":'.json_encode($added_modids,JSON_UNESCAPED_SLASHES).', "pretty_name": '.json_encode($added_pretty_names,JSON_UNESCAPED_SLASHES).', "author": '.json_encode($added_authors,JSON_UNESCAPED_SLASHES).',"mcversion":'.json_encode($added_mcversions,JSON_UNESCAPED_SLASHES).',"version": '.json_encode($added_versions,JSON_UNESCAPED_SLASHES);

// if we got warnings
if (!empty($warn) && sizeof($warn)>0) {
    // accumulate them all into one, as we dont support displaying multiple seperate ones in index.php.
    $acc_message='';
    foreach ($warn as $w) {
        $acc_message.=$w.' ';
    }
    echo '{"status":"warn","message":"'.$acc_message.'Issue(s) must be addressed before using this mod.", '.$moredata.'}';
} else {
    // get the last one added. we don't support displaying multiple..
    // todo: support displaying multiple in index.php
    // modid appears to be the id in the database, name appears to be modid...
    error_log('{"status":"succ","message":"Mod added.", '.$moredata.'}');
    die('{"status":"succ","message":"Mod added.", '.$moredata.'}');
}

exit();
?>
