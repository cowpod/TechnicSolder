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
// if (!isset($db)){
//     $db=new Db;
//     $db->connect();
// }

// fiels = name of input in index.php
$file_name = $_FILES["fiels"]["name"];
$file_tmp = $_FILES["fiels"]["tmp_name"];

if (empty($file_tmp)) {
    die ('{"status":"error","message":"File is too big! Check your post_max_size (current value '.ini_get('post_max_size').') and upload_max_filesize (current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}');
}

require('toml.php');
require('slugify.php');
require('interval_range_utils.php');
require('compareZipContents.php');

define('FABRIC_INFO_PATH', 'fabric.mod.json');
define('FORGE_INFO_PATH', 'META-INF/mods.toml');
define('FORGE_OLD_INFO_PATH', 'mcmod.info');

// todo: implement warning system for missing mod info

function getModTypes(string $filePath): array {
    // returns a dictionary ['fabric'=>bool,'forge'=>bool,'forge_old'=>bool] 
    // representing each detected mod type
    // yes, there can be multiple types of the same mod in a file.

    // todo: or even multiple mods in one file, but we're not handling that.

    // todo: check finfo to ensure it's actually a zip

    assert (file_exists($filePath));
    $zip = new ZipArchive;
    $open_zip = $zip->open($filePath);
    if ($open_zip !== TRUE) {
        $zip->close();
        return [];
    }
    $check_fabric = $zip->statName(FABRIC_INFO_PATH);
    $check_forge = $zip->statName(FORGE_INFO_PATH); // MC 1.13+
    $check_forge_old = $zip->statName(FORGE_OLD_INFO_PATH); // MC 1.12-
    $zip->close();

    return ['fabric'=> $check_fabric!==FALSE, 'forge'=> $check_forge!==FALSE, 'forge_old'=> $check_forge_old!==FALSE];
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

    // only make a copy of this object.
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
    if ($zip->open($filePath) === FALSE)
        die ('{"status": "error", "message": "Could not open JAR file as ZIP"}');

    if ($modTypes['forge']===TRUE) {

        $raw = $zip->getFromName(FORGE_INFO_PATH);
        if ($raw === FALSE)
            die ('{"status": "error", "message": "Could not access info file from Forge mod."}');

        $toml = new Toml;
        $parsed = $toml->parse($raw);

        $mod_info['forge']=$mcmod_orig;

        // there can be multiple mods entries, we are just getting the first.
        foreach ($parsed['mods'] as $mod) {
            if (empty($mod['modId']))
                die ('{"status": "error", "message": "Forge info file does not contain modId!"}');
            else
                $mod_info['forge']['modid'] = strtolower($mod['modId']);
            if (empty($mod['version']))
                die ('{"status": "error", "message": "Forge info file does not contain version!"}');
            else {
                if ($mod['version']=='${file.jarVersion}')
                    $mod['version']='';
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

        $mod_info['forge']['loadertype'] = 'forge';
        if (empty($parsed['loaderVersion']))
            die ('{"status": "error", "message": "Forge info file does not contain loaderVersion!"}');
        else
            $mod_info['forge']['loaderversion'] = $parsed['loaderVersion'];
        if (!empty($parsed['license']))
            $mod_info['forge']['license'] = $parsed['license'];
    }

    if ($modTypes['forge_old']===TRUE) {

        $raw = $zip->getFromName(FORGE_OLD_INFO_PATH);
        if ($raw === FALSE)
            die ('{"status": "error", "message": "Could not access info file from Old Forge mod."}');


        $mod_info['forge_old']=$mcmod_orig;

        // dictionary is nested in an array
        $parsed = json_decode(preg_replace('/\r|\n/', '', trim($raw)), true)[0];
        if (empty($parsed['modid']))
            die ('{"status": "error", "message": "Old Forge info file does not contain modid!"}');
        else
            $mod_info['forge_old']['modid'] = strtolower($parsed['modid']);
        if (empty($parsed['version']))
            die ('{"status": "error", "message": "Old Forge info file does not contain version!"}');
        else {
            if ($parsed['version']=='${file.jarVersion}')
                $parsed['version']='';
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
        $mod_info['forge_old']['mcversion']=$parsed['mcversion'];

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
        if ($raw === FALSE)
            die ('{"status": "error", "message": "Could not access info file from Fabric mod."}');


        $mod_info['fabric']=$mcmod_orig;

        $parsed = json_decode(preg_replace('/\r|\n/', '', trim($raw)), true);

        if (empty($parsed['id']))
            die ('{"status": "error", "message": "Fabric info file doe not contain id!"}');
        else
            $mod_info['fabric']['modid'] = $parsed['id'];
        if (empty($parsed['version']))
            die ('{"status": "error", "message": "Fabric info file does not contain version!"}');
        else
            $mod_info['fabric']['version'] = $parsed['version'];
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
                $dep_version = ''; // '' means any...

                if (!empty($parsed['depends'][$depId])) {// unlikely, given data structure
                    $dep_version_r = $parsed['depends'][$depId];
                    $matches=[];

                    // >= greater than equal to
                    if (preg_match('/^>=(.*)/', $dep_version_r, $matches)) {
                        if (!empty($matches) && !empty($matches[1])) {
                            $dep_version = '['.$matches[1].',)';
                        }
                    }
                    // < greater than
                    elseif (preg_match('/^>(.*)/', $dep_version_r, $matches)) {
                        if (!empty($matches) && !empty($matches[1])) {
                            $dep_version = '('.$matches[1].',)';
                        }
                    }
                    // <= less than equal to
                    elseif (preg_match('/^<=(.*)/', $dep_version_r, $matches)) {
                        if (!empty($matches) && !empty($matches[1])) {
                            $dep_version = '(,'.$matches[1].']';
                        }
                    }
                    // < less than 
                    elseif (preg_match('/^<(.*)/', $dep_version_r, $matches)) {
                        if (!empty($matches) && !empty($matches[1])) {
                            $dep_version = '(,'.$matches[1].')';
                        }
                    }
                    // ~ approximately (major version)
                    elseif (preg_match('/^~(.*)/', $dep_version_r, $matches)) {
                        if (!empty($matches) && !empty($matches[1])) {
                            $matches_approx=[];
                            if (preg_match('/^([0-9]+\.[0-9]+)/', $matches[1], $matches_approx)) {
                                if (!empty($matches_approx) && !empty($matches_approx[1])) { // todo: check that [1] is correct
                                    $dep_version = $matches_approx[1];
                                }
                            }
                        }
                    }
                    // * any
                    elseif ($dep_version_r=='*') { 
                        // '' means any...
                    } 
                    // exact
                    else {
                        $dep_version = $dep_version_r;

                        // if it ends in .x then it's also in interval notation!
                        if (str_ends_with($dep_version, '.x')) {
                            $nstr=substr($dep_version, 0, -2);
                            $nstr_exp=explode('.',$nstr);
                            $nstr_exp[sizeof($nstr_exp)-1]=strval(intval($nstr_exp[sizeof($nstr_exp)-1])+1);
                            $dep_version = '['.$nstr.','.implode('.',$nstr_exp).')';
                        }
                    }
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

        $mod_info['fabric']['loadertype']='fabric';

        if (!empty($parsed['license']))
            $mod_info['fabric']['license'] = $parsed['license'];
    }

    return $mod_info;
}

function processFile(string $filePath, string $fileName, array $modinfo): int {
    // todo: it is possible to craft a 'bad' modinfo data entry. mitigate it?

    if (!is_dir('../mods/tmp')) {
        mkdir('../mods/tmp',0755,TRUE);
    }

    global $db;
    global $config;

    $mod_zip_name = $modinfo['modid'].'-'.slugify2($modinfo['mcversion']).'-'.$modinfo['version'].'.zip';
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

            $mcvrange = mcversion_to_range($modinfo['mcversion']);
            $mcvmin = !empty($mcvrange['min']) ? $mcvrange['min'] : ''; 
            $mcvmax = !empty($mcvrange['max']) ? $mcvrange['max'] : '';

            $addq = $db->execute("INSERT INTO `mods` (`name`,`pretty_name`,`md5`,`url`,`link`,`author`,`description`,`version`,`mcversion`,`mcversion_low`,`mcversion_high`,`filename`,`type`,`loadertype`) VALUES ("
                ."'".$db->sanitize($modinfo['modid'])."',"
                ."'".$db->sanitize($modinfo['name'])."',"
                ."'".$db->sanitize($mod_zip_md5)."',"
                ."'"."',"
                ."'".$db->sanitize($modinfo['url'])."',"
                ."'".$db->sanitize($modinfo['authors'])."',"
                ."'".$db->sanitize($modinfo['description'])."',"
                ."'".$db->sanitize($modinfo['version'])."',"
                ."'".$db->sanitize($modinfo['mcversion'])."',"
                ."'".$db->sanitize($mcvmin)."',"
                ."'".$db->sanitize($mcvmax)."',"
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
            die ('{"status":"error","message":"Could not create zip. Out of disk space?"}');
        }
    } else {
        die ('{"status":"error","message":"Could not create zip. Bad folder permissions?"}');
    }
}
// todo: specify in config if it's http or https!

if (!file_exists($file_tmp))
    die ('{"status":"error","message":"Uploaded file does not exist!?"}');

$modTypes = getModTypes($file_tmp);
// error_log('MODTYPES: '.json_encode($modTypes));
$modInfos = getModInfos($modTypes, $file_tmp);
// error_log('MODINFOS: '.json_encode($modInfos));

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
        die('{"status": "error","message":"Mod already in database!"}');
    }
    // consequence: we allow multiple loadertypes (ie fabric, forge) of the exact same mod
    $result_mod_id = processFile($file_tmp, $file_name, $modinfo);
    if ($result_mod_id === -1) {
        die('{"status":"error","message":"Unable to add mod to database.", "name": "'.$file_name.'"}');
    } else {
        error_log('Successfully added modid='.$modinfo['modid'].' id='.$result_mod_id.' to database!');
        array_push($added_modids, $modinfo['modid']);
        array_push($added_ids, $result_mod_id);
    }
}

$db->disconnect();

// get the last one added. we don't support displaying multiple..
// todo: support displaying multiple in index.php
// modid appears to be the id in the database, name appears to be modid...
error_log('{"status":"succ","message":"Mod has been uploaded and saved.","modid":'.$added_ids[sizeof($added_ids)-1].',"name":"'.$added_modids[sizeof($added_modids)-1].'"}');
echo '{"status":"succ","message":"Mod has been uploaded and saved.","modid":'.$added_ids[sizeof($added_ids)-1].',"name":"'.$added_modids[sizeof($added_modids)-1].'"}';

exit();
?>
