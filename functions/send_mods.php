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
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$fileName = $_FILES["fiels"]["name"];
$fileJarInTmpLocation = $_FILES["fiels"]["tmp_name"];
if (!$fileJarInTmpLocation) {
    echo '{"status":"error","message":"File is too big! Check your post_max_size (current value '.ini_get('post_max_size').') andupload_max_filesize (current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}';
    exit();
}
require('toml.php');
require('slugify.php');

$fileNameTmp = explode("-",slugify($fileName));
array_pop($fileNameTmp);
$fileNameShort=implode("-",$fileNameTmp);
$fileNameZip=$fileNameShort.".zip";
$fileName=$fileNameShort.".jar";
$fileJarInFolderLocation="../mods/mods-".$fileNameShort."/".$fileName;
$fileZipLocation="../mods/".$fileNameZip;
$fileInfo=array();
if (!file_exists("../mods/mods-".$fileNameShort)) {
    mkdir("../mods/mods-".$fileNameShort);
} else {
    echo '{"status":"error","message":"Folder mods-'.$fileNameShort.' already exists! Please remove it and try again."}';
    exit();
}
$name="";
function processFile($zipExists, $md5) {
    global $fileName;
    global $fileNameZip;
    global $fileNameShort;
    global $fileJarInFolderLocation;
    global $fileZipLocation;
    global $db;
    global $warn;
    global $fileInfo;
    global $name;

    $legacy=false;
    $mcmod=array();
    
    // error_log('pf start');

    if (@$result=file_get_contents("zip://".realpath($fileJarInFolderLocation)."#META-INF/mods.toml")) {
        // is a 1.14+ forge mod
        // error_log("FORGE");

        // todo: technically it's possible to have multiple mods in one file, which the toml parser supports. however we (and the majority of others) don't support this.
        $toml = new Toml;
        $mcmod = $toml->parse($result);
        if (sizeof($mcmod['mods'])==0) {
            echo '{"status":"error","message":"Could not parse TOML->mods->0."}';
        }
        $mcmod['mods']=$mcmod['mods'][0]; 

        // file_put_contents('toml_output.json', JSON_ENCODE($mcmod), FILE_APPEND);

        if (! (array_key_exists('modId', $mcmod['mods'])
            && array_key_exists('version', $mcmod['mods'])
            && array_key_exists('displayName', $mcmod['mods'])
            && (array_key_exists('author', $mcmod['mods']) || array_key_exists('authors', $mcmod['mods']))
            && array_key_exists('description', $mcmod['mods']))) {
            $warn['b'] = true;
            $warn['level'] = "info";
            $warn['message'] = "There is some information missing in mcmod.info.";
        }

    // This Fabric section DOES NOT WORK.
    // currently, before this function is called the jar is uploaded and placed in a folder called mods.
    // it is also renamed to name-version.JAR, and then zipped (we have a zip file, contianing a mod folder,
    // containing a zip file renamed to jar, containing a mod folder, containing a jar file)
    // todo: simply extract the fabric ZIP so we have mod->JAR which is what we expect later in this
    // function

    } elseif (@$result=file_get_contents("zip://".realpath("../mods/mods-".$fileName."/".$fileName)."#fabric.mod.json")) {
        // is a fabric mod
        // error_log("FABRIC");

        $result = file_get_contents("zip://" . realpath("../mods/mods-" . $fileName . "/" . $fileName) . "#fabric.mod.json");
        $q = json_decode(preg_replace('/\r|\n/', '', trim($result)), true);
        $mcmod = $q;

        // fill $mcmod['mods']
        $mcmod['mods']=[];
        $mcmod['mods']["modId"] = $mcmod["id"];
        $mcmod['mods']["url"] = $mcmod["contact"]["sources"];
        $mcmod['mods']["version"] = $mcmod["version"];
        $mcmod['mods']["displayName"] = $mcmod["name"];
        $mcmod['mods']["authors"] = $mcmod["authors"];
        $mcmod['mods']["displayURL"] = implode("\n", $mcmod["contact"]);

        // fill $mcmod['dependencies'][$mcmod['mods']['modId']]
        $mcmod['dependencies']=[];
        $mcmod['dependencies'][$mcmod['id']]=[];
        foreach (array_keys($mcmod['depends']) as $depId) {
            $depMcVersion=$mcmod['depends'][$depId];
            $newdep=[];
            $newdep['modId']=$depId;

            // >=
            $matches=[];
            if (preg_match("/^>=(.*)/", $depMcVersion, $matches)) {
                $newdep['versionRange']="[".$matches[1].",)";
            }
            // <=
            elseif (preg_match("/<=(.*)/", $depMcVersion, $matches)) {
                $v=$matches[1];
                $newdep['versionRange']="(,".$matches[1]."]";
            }
            // ~
            elseif (preg_match("/^~(.*)/", $depMcVersion, $matches)) {
                $newdep['versionRange']=$matches[1];
            }

            array_push($mcmod['dependencies'][$mcmod['id']], $newdep);
        }

        // error_log(json_encode($mcmod));
        
        if (!$mcmod['modid'] || !$mcmod['name'] || !$mcmod['description'] || !$mcmod['version'] || !$mcmod['mcversion'] || !$mcmod['url'] || !$mcmod['authorList']) {
            $warn['b'] = true;
            $warn['level'] = "info";
            $warn['message'] = "There is some information missing in fabric.mod.json.";
        }
    } elseif (@$result=file_get_contents("zip://".realpath($fileJarInFolderLocation)."#mcmod.info")) {
        // is legacy mod
        // error_log("LEGACY");

        $legacy=true;
        $mcmod = json_decode(preg_replace('/\r|\n/','',trim($result)),true)[0];
        if (!$mcmod['modid']||!$mcmod['name']||!$mcmod['description']||!$mcmod['version']||!$mcmod['mcversion']||!$mcmod['url']||!$mcmod['authorList']) {
            $warn['b'] = true;
            $warn['level'] = "info";
            $warn['message'] = "There is some information missing in mcmod.info.";
        }
    } else {
        // we don't know what type of mod this is...
        // error_log("IDFK");

        $warn['b'] = true;
        $warn['level'] = "warn";
        $warn['message'] = "File does not contain any type of known mod info. Manual configuration required.";
    }


    // error_log('pf zipcheck');

    if (!$zipExists) {
        $zip = new ZipArchive();
        if ($zip->open($fileZipLocation, ZIPARCHIVE::CREATE) !== TRUE) {
            echo '{"status":"error","message":"Could not open archive"}';
        }
        $zip->addEmptyDir('mods');
        if (is_file($fileJarInFolderLocation)) {
            $zip->addFile($fileJarInFolderLocation, "mods/".$fileName) or die ('{"status":"error","message":"Could not add file $key"}');
        }
        $zip->close();
    }


    if ($legacy) {
        // legacy forge 1.14-

        // error_log('legacy');

        if (!$mcmod['name']) {
            $pretty_name = $fileNameShort;
        } else {
            $pretty_name = $mcmod['name'];
        }
        if (!$mcmod['modid']) {
            $name = slugify($pretty_name);
        } else {
            if (@preg_match("^[a-z0-9]+(?:-[a-z0-9]+)*$", $mcmod['modid'])) {
                $name = $mcmod['modid'];
            } else {
                $name = slugify($mcmod['modid']);
            }
        }
        $link = $mcmod['url'];
        $author = implode(', ', $mcmod['authorList']);
        $description = $mcmod['description'];
        $version = $mcmod['version'];
        $mcversion = $mcmod['mcversion'];
    } else { // forge 1.14+, fabric
        // error_log('forge/fabric');

        $pretty_name = array_key_exists('displayName', $mcmod['mods']) ? $mcmod['mods']['displayName'] : "";

        $name = "";
        if (array_key_exists('modId', $mcmod['mods'])) {
            if (preg_match("/^[a-z0-9]+(?:-[a-z0-9]+)*$/", $mcmod['mods']['modId'])) {
                $name = $mcmod['mods']['modId'];
            } else {
                $name = slugify($mcmod['mods']['modId']);
            }
        } else {
            // get the slug from the filename
            $exploded_filename = explode("-", $fileInfo['filename']);
            if (sizeof($exploded_filename)>0) {
                $name=$exploded_filename[0];
            } else {
                $warn['b'] = true;
                $warn['level'] = "info";
                $warn['message'] = "There is some information missing in mcmod.info.";
            }
        }

        $link = array_key_exists('displayURL', $mcmod['mods']) ? $mcmod['mods']['displayURL'] : "";

        $author = array_key_exists('author', $mcmod['mods']) ? $mcmod['mods']['author'] : $mcmod['mods']['authors'];

        $description = array_key_exists('description', $mcmod['mods']) ? $mcmod['mods']['description'] : "";

        $mcversion=""; // (bad) placeholder
        // attempt to pull out necessary dependency information from toml
        if (array_key_exists('dependencies', $mcmod)) {
            if (array_key_exists($name, $mcmod['dependencies'])) {
                foreach ($mcmod['dependencies'][$name] as $dependency) {

                    $dependency_modId=$dependency['modId'];
                    // $dependency_mandatory=$dependency['mandatory'];
                    $dependency_versionRange=$dependency['versionRange'];
                    // $dependency_ordering=$dependency['ordering'];
                    // $dependency_side=$dependency['side'];

                    if ($dependency_modId=='minecraft') {
                        // we currently just need the dependent minecraft version.
                        $mcversion=$dependency_versionRange;
                        break;
                    }

                    // todo: check other dependencies of this mod
                }
            } else {
                // file_put_contents("error.log","couldn't find dependencies!\n", FILE_APPEND);
            }
        } else {
            // file_put_contents("error.log","couldn't find dependencies!\n", FILE_APPEND);
        }

        // see if loaderVersion matches something in forges...
        if ($mcversion==="" && array_key_exists('loaderVersion',$mcmod)) {
            require('interval_range_utils.php');
            $querystring = "SELECT version,mcversion FROM mods WHERE type='forge' ORDER BY version ASC";
            $query = $db->query($querystring);
            if (sizeof($query)>0) { // if we even have any forges
                foreach ($query as $row) {
                    // file_put_contents("../status.log", "got a forge: ".JSON_ENCODE($row)."\n", FILE_APPEND);
                    if (in_range($row['version'], $mcmod['loaderVersion'])) {
                        // file_put_contents("../status.log", "got mcversion: ".$row['mcversion']."\n", FILE_APPEND);
                        $mcversion=$row['mcversion'];

                        // this is still considered missing, since our forge versions specify a lower bound but no upper bound. ie. we may over estimate the compatible minecraft version.
                        $warn['b'] = true;
                        $warn['level'] = "info";
                        $warn['message'] = "There is some information missing in mcmod.info.";
                        break;
                    }
                }
            }
        }
        
        // if we're still missing mcversion, just report it to the user...
        if ($mcversion==="") {
            $warn['b'] = true;
            $warn['level'] = "info";
            $warn['message'] = "There is some information missing in mcmod.info.";
        }

        $version = $mcmod['mods']['version'];
        // if needed, attempt to pull out version from filename
        if ($version == "\${file.jarVersion}" ) {
            $tmpFilename=explode('-', $fileNameShort);
            array_shift($tmpFilename);
            $tmpFilename = implode('.', $tmpFilename);
            $version = $tmpFilename;
        }

        if ($version == "\${file.jarVersion}") {
            $warn['b'] = true;
            $warn['level'] = "info";
            $warn['message'] = "There is some information missing in mcmod.info.";
        }
    }

    // calculate md5 if we just created the zip
    if (!$zipExists) {
        $md5 = md5_file("../mods/".$fileInfo['filename'].".zip");
    }

    //$url = "http://".$config['host'].$config['dir']."mods/".$fileInfo['filename'].".zip";

    $res_query_string="INSERT INTO `mods` (`name`,`pretty_name`,`md5`,`url`,`link`,`author`,`description`,`version`,`mcversion`,`filename`,`type`) VALUES ("."'"   .$db->sanitize($name)
        ."', '".$db->sanitize($pretty_name)
        ."', '".$db->sanitize($md5)
        ."', '".$db->sanitize("")
        ."', '".$db->sanitize($link)
        ."', '".$db->sanitize($author)
        ."', '".$db->sanitize($description)
        ."', '".$db->sanitize($version)
        ."', '".$db->sanitize($mcversion)
        ."', '".$db->sanitize($fileNameZip)
        ."', 'mod')";
    
    // file_put_contents("error.log", $res_query_string, FILE_APPEND);

    $res = $db->query($res_query_string);
    if ($res) {
        if (@$warn['b']==true) {
            if ($warn['level']=="info") {
                echo '{"status":"info","message":"'.$warn['message'].'","modid":'.$db->insert_id().'}';
            } else {
                echo '{"status":"warn","message":"'.$warn['message'].'","modid":'.$db->insert_id().'}';
            }

        } else {
            echo '{"status":"succ","message":"Mod has been uploaded and saved.","modid":'.$db->insert_id().',"name":"'.$fileName.'"}';
        }
    } else {
        echo '{"status":"error","message":"Mod could not be added to database"}';
    }
}

if (move_uploaded_file($fileJarInTmpLocation, $fileJarInFolderLocation)) {
    $fileInfo = pathinfo($fileJarInFolderLocation);
    if (file_exists($fileZipLocation)) {
        $md5_1 = md5_file($fileJarInFolderLocation);
        $md5_2 = md5_file("zip://".realpath($fileZipLocation)."#mods/".$fileName);
        if ($md5_1 !== $md5_2) {
            echo '{"status":"error","message":"File with name \''.$fileName.'\' already exists!","md51":"'.$md5_1.'","md52":"'.$md5_2.'","zip":"'.$fileJarInFolderLocation.'"}';
        } else {
            $fq = $db->query("SELECT `id` FROM `mods` WHERE `filename` = '".$fileNameZip."'");
            if (sizeof($fq)==1) {
                $fq=$fq[0];
                echo '{"status":"info","message":"This mod is already in the database.","modid":'.$fq['id'].',"name":"'.$fileName.'"}';
            } else {
                processFile(true, $md5_1); // use existing zip
            }
        }
    } else {
        processFile(false, ''); // create zip
    }
    unlink($fileJarInFolderLocation);
    rmdir("../mods/mods-".$fileNameShort);

} else {
    echo '{"status":"error","message":"Permission denied! Please open SSH and run \'chown -R www-data '.addslashes(dirname(dirname(get_included_files()[0]))).'\'"}';
}
?>
