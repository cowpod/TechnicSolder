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

require('toml.php');
require('slugify.php');
require('interval_range_utils.php');
require('compareZipContents.php');

define('FABRIC_INFO_PATH', 'fabric.mod.json');
define('FORGE_INFO_PATH', 'META-INF/mods.toml');
define('FORGE_OLD_INFO_PATH', 'mcmod.info');

$error=[];
$warn=[];
$info=[];
$succ=[];

function getModTypes(string $filePath): array {
    // returns a dictionary ['fabric'=>bool,'forge'=>bool,'forge_old'=>bool] 
    // representing each detected mod type
    // yes, there can be multiple types of the same mod in a file.

    // todo: or even multiple mods in one file, but we're not handling that.

    // todo: check finfo to ensure it's actually a zip

    error_log("getModTypes()");
    assert (file_exists($filePath));

    $has_fabric=FALSE;
    $has_forge=FALSE;
    $has_forge_old=FALSE;

    $zip = new ZipArchive;

    if ($zip->open($filePath)===TRUE) {
        // statName returns Array or FALSE.
        $has_fabric = $zip->statName(FABRIC_INFO_PATH)!==FALSE;
        $has_forge = $zip->statName(FORGE_INFO_PATH)!==FALSE; // MC 1.13+
        $has_forge_old = $zip->statName(FORGE_OLD_INFO_PATH)!==FALSE; // MC 1.12-
        $zip->close();
    }

    return ['fabric'=> $has_fabric, 'forge'=> $has_forge, 'forge_old'=> $has_forge_old];
}

function getModInfos(array $modTypes, string $filePath): array {
    /*
    We don't support multiple mods in a file. 
    TODO: support multiple mods in one file.

    Returns a dictionary of 
    [
    'forge' => [info] or null, for forge
    'forge_old' => [info] or null, for old forge
    'fabric' => [info] or null, for fabric
    ]

    Format is similar to that of 1.12 and below forge mcmod.info.
    Except we don't support multiple mods in a file. 
    // (Note the following ISN'T a nested dictionary in an array.)

    */
    global $error;
    global $warn;
    global $info;
    global $succ;

    error_log("getModInfos()");

    // only make a copy of this object. we don't have const or final :c
    $mcmod_orig = [
        'modid'         =>null, // mandatory 
        'version'       =>null, // mandatory
        'name'          =>null,
        'url'           =>null,
        'credits'       =>null,
        'authors'       =>null,
        'description'   =>null,
        'mcversion'     =>null, // pulled from dependencies, exact or interval notation
        'dependencies'  =>[],   // pulled from dependencies, ['dep mod id']
        'loaderversion' =>null, 
        'loadertype'    =>null, 
        'license'       =>null
    ];

    $mod_info = ['forge'=>null, 'forge_old'=>null, 'fabric'=>null];

    $zip = new ZipArchive();
    if ($zip->open($filePath) === FALSE) {
        error_log ('{"status": "error", "message": "Could not open JAR file as ZIP"}');
        die ('{"status": "error", "message": "Could not open JAR file as ZIP"}');
    }

    if ($modTypes['forge']===TRUE) {
        $raw = $zip->getFromName(FORGE_INFO_PATH);
        if ($raw === FALSE) {
            error_log  ('{"status": "error", "message": "Could not access info file from Forge mod."}');
            die ('{"status": "error", "message": "Could not access info file from Forge mod."}');
        }

        $toml = new Toml;
        $parsed = $toml->parse($raw);
        $mod_info['forge']=$mcmod_orig;

        // there can be multiple mods entries, we are just getting the first.
        foreach ($parsed['mods'] as $mod) {
            if (empty($mod['modId'])) {
                error_log ('{"status": "error", "message": "Missing modId!"}');
                die ('{"status": "error", "message": "Missing modId!"}');
            } else {
                $mod_info['forge']['modid'] = strtolower($mod['modId']);
            }
            if (empty($mod['version']) || $mod['version']=='${file.jarVersion}') {
                array_push($warn, 'Missing version!');
                $mod_info['forge']['version'] = '';
            } else {
                $mod_info['forge']['version'] = $mod['version'];
            }
            if (!empty($mod['displayName']))
                $mod_info['forge']['name'] = $mod['displayName'];
            if (!empty($mod['displayURL']))
                $mod_info['forge']['url'] = $mod['displayURL'];
            if (!empty($mod['credits']))
                $mod_info['forge']['credits'] = $mod['credits'];
            if (!empty($mod['authors']))
                $mod_info['forge']['authors'] = $mod['authors'];
            if (!empty($mod['description']))
                $mod_info['forge']['description'] = $mod['description'];
            break;
        }

        // handle dependencies and get mcversion, sometimes there can be none.
        if (!empty($parsed['dependencies']) && !empty($parsed['dependencies'][$mod_info['forge']['modid']])) {
            // each dependency is an indexed array entry.
            foreach ($parsed['dependencies'][$mod_info['forge']['modid']] as $dep) {
                if (empty($dep['modId']))
                    continue;
                if (strtolower($dep['modId'])=='minecraft') {
                    $mod_info['forge']['mcversion'] = $dep['versionRange'];
                }
                array_push($mod_info['forge']['dependencies'], strtolower($dep['modId']));
            }
        }

        if (empty($mod_info['forge']['mcversion'])) {
            array_push($warn, 'Missing mcversion!');
        }

        $mod_info['forge']['loadertype'] = 'forge';
        if (empty($parsed['loaderVersion'])) {
            error_log ('{"status": "error", "message": "Missing loaderVersion!"}');
            die ('{"status": "error", "message": "Missing loaderVersion!"}');
        } else {
            $mod_info['forge']['loaderversion'] = $parsed['loaderVersion'];
        }
        if (!empty($parsed['license']))
            $mod_info['forge']['license'] = $parsed['license'];
    }

    if ($modTypes['forge_old']===TRUE) {

        $raw = $zip->getFromName(FORGE_OLD_INFO_PATH);
        if ($raw === FALSE) {
            error_log ('{"status": "error", "message": "Could not access info file from Old Forge mod."}');
            die ('{"status": "error", "message": "Could not access info file from Old Forge mod."}');
        }

        $mod_info['forge_old']=$mcmod_orig;

        // dictionary is nested in an array
        $parsed = json_decode(preg_replace('/\r|\n/', '', trim($raw)), true)[0];
        if (empty($parsed['modid'])) {
            error_log ('{"status": "error", "message": "Missing modid!"}');
            die ('{"status": "error", "message": "Missing modid!"}');
        } else {
            $mod_info['forge_old']['modid'] = strtolower($parsed['modid']);
        }
        if (empty($parsed['version']) || $parsed['version']=='${file.jarVersion}') {
            array_push($warn, 'Missing version!');
            $mod_info['forge_old']['version'] = '';
        } else {
            $mod_info['forge_old']['version'] = $parsed['version'];
        }
        if (!empty($parsed['name']))
            $mod_info['forge_old']['name']  = $parsed['name'];
        if (!empty($parsed['url']))
            $mod_info['forge_old']['url'] = $parsed['url'];
        if (!empty($parsed['credits'])) 
            $mod_info['forge_old']['credits'] = $parsed['credits'];
        if (!empty($parsed['authorList'])) 
            $mod_info['forge_old']['authors'] = implode(', ', $parsed['authorList']);
        if (!empty($parsed['description'])) 
            $mod_info['forge_old']['description'] = $parsed['description'];
        if (!empty($parsed['mcversion']))
            $mod_info['forge_old']['mcversion']=$parsed['mcversion'];
        else {
            array_push($warn, 'Missing mcversion!');
            // error_log('{"status": "warn", "message": "Missing mcversion!"}');
        }

        // each dependency is a string
        if (array_key_exists('dependencies', $parsed)) {
            foreach ($parsed['dependencies'] as $dep) {
                if (empty($dep)) 
                    continue;
                array_push($mod_info['forge_old']['dependencies'], strtolower($dep));;
            }
        }

        $mod_info['forge_old']['loadertype']='forge'; // same
        // no loaderversion
        // no license
    }

    if ($modTypes['fabric']===TRUE) {
        $raw = $zip->getFromName(FABRIC_INFO_PATH);
        if ($raw === FALSE) {
            error_log ('{"status": "error", "message": "Could not access info file from Fabric mod."}');
            die ('{"status": "error", "message": "Could not access info file from Fabric mod."}');
        }

        $mod_info['fabric']=$mcmod_orig;
        $parsed = json_decode(preg_replace('/\r|\n/', '', trim($raw)), true);

        if (empty($parsed['id'])) {
            error_log ('{"status": "error", "message": "Missing id!"}');
            die ('{"status": "error", "message": "Missing id!"}');
        } else {
            $mod_info['fabric']['modid'] = $parsed['id'];
        }
        if (empty($parsed['version'])) {
            array_push($warn, 'Missing version!');
            // error_log('{"status": "warn", "message": "Missing version!"}');
            $mod_info['fabric']['version'] = '';
        } else{
            $mod_info['fabric']['version'] = $parsed['version'];
        }
        if (!empty($parsed['name']))
            $mod_info['fabric']['name'] = $parsed['name'];
        if (!empty($parsed['contact']) && !empty($parsed['contact']['homepage']))
            $mod_info['fabric']['url'] = $parsed['contact']['homepage'];
        // no credits
        if (!empty($parsed['authors']))
            $mod_info['fabric']['authors'] = implode(', ', $parsed['authors']);
        if (!empty($parsed['description']))
            $mod_info['fabric']['description'] = $parsed['description'];

        // each dependency is a key=value entry.
        if (array_key_exists('depends', $parsed)) {
            foreach (array_keys($parsed['depends']) as $depId) {
                $dep_version = '*'; // default to any
                if (!empty($parsed['depends'][$depId])) {
                    $dep_version = fabric_to_interval_range($parsed['depends'][$depId]);
                }

                array_push($mod_info['fabric']['dependencies'], $dep_version);

                if (strtolower($depId)=='minecraft') {
                    $mod_info['fabric']['mcversion']=$dep_version;
                }
                if (strtolower($depId)=='fabricloader') {
                    $mod_info['fabric']['loaderversion']=$dep_version;
                }
            }
        }

        if (empty($mod_info['fabric']['mcversion'])) {
            array_push($warn, 'Missing mcversion!');
            // error_log('{"status": "warn", "message": "Missing mcversion!"}');
        }

        $mod_info['fabric']['loadertype']='fabric';

        if (!empty($parsed['license']))
            $mod_info['fabric']['license'] = $parsed['license'];
    }

    return $mod_info;
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
// todo: specify in config if it's http or https!

if (!file_exists($file_tmp)){
    error_log ('{"status":"error","message":"Uploaded file does not exist!?"}');
    die ('{"status":"error","message":"Uploaded file does not exist!?"}');
}

$modTypes = getModTypes($file_tmp);
$modInfos = getModInfos($modTypes, $file_tmp);

if (!isset($db)){
    $db=new Db;
    $db->connect();
}

// we only care about the first mod.
// todo: in delete modv, add a check to see if the file is used by another mod entry.
// ie. same file for both forge and fabric loader types!
$added_ids=[];
$added_modids=[];
foreach ($modInfos as $modinfo) {
    if (empty($modinfo))
        continue;

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
}

$db->disconnect();

// error_log(json_encode($added_ids));

if (sizeof($added_ids)==0) {
    error_log('{"status":"error","message":"Unable to add mod to database.", "name": "'.$file_name.'"}');
    die('{"status":"error","message":"Unable to add mod to database.", "name": "'.$file_name.'"}');
}

// if we got warnings
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
