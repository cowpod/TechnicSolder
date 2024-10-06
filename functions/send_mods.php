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

require("db.php");
$db=new Db;
$db->connect();

$fileName = $_FILES["fiels"]["name"];
$fileJarInTmpLocation = $_FILES["fiels"]["tmp_name"];
if (!$fileJarInTmpLocation) {
    echo '{"status":"error","message":"File is too big! Check your post_max_size (current value '.ini_get('post_max_size').') andupload_max_filesize (current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}';
    exit();
}
require('toml.php');

function slugify($text) {
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);
  //$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  $text = preg_replace('~[^-\w]+~', '', $text);
  $text = trim($text, '-');
  $text = preg_replace('~-+~', '-', $text);
  $text = strtolower($text);
  if (empty($text)) {
    return 'n-a';
  }
  return $text;
}
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
function processFile($zipExists, $md5) {
    global $fileName;
    global $fileNameZip;
    global $fileNameShort;
    global $fileJarInFolderLocation;
    global $fileZipLocation;
    global $conn;
    global $warn;
    global $fileInfo;

    $legacy=false;
    $mcmod=array();
    $result = @file_get_contents("zip://".realpath($fileJarInFolderLocation)."#META-INF/mods.toml");
    if (!$result) {
        # fail 1.14+ or fabric mod check
        $result = file_get_contents("zip://".realpath($fileJarInFolderLocation)."#mcmod.info");
        if (!$result) {
            # fail legacy mod check
            $warn['b'] = true;
            $warn['level'] = "warn";
            $warn['message'] = "File does not contain mod info. Manual configuration required.";
        } elseif (file_get_contents("zip://".realpath("../mods/mods-".$fileName."/".$fileName)."#fabric.mod.json")) {
            # is a fabric mod
            $result = file_get_contents("zip://" . realpath("../mods/mods-" . $fileName . "/" . $fileName) . "#fabric.mod.json");
            $q = json_decode(preg_replace('/\r|\n/', '', trim($result)), true);
            $mcmod = $q;
            $mcmod["modid"] = $mcmod["id"];
            $mcmod["url"] = $mcmod["contact"]["sources"];
            if (!$mcmod['modid'] || !$mcmod['name'] || !$mcmod['description'] || !$mcmod['version'] || !$mcmod['mcversion'] || !$mcmod['url'] || !$mcmod['authorList']) {
                $warn['b'] = true;
                $warn['level'] = "info";
                $warn['message'] = "There is some information missing in fabric.mod.json.";
            }
        } else {
            # is legacy mod
            $legacy=true;
            $mcmod = json_decode(preg_replace('/\r|\n/','',trim($result)),true)[0];
            if (!$mcmod['modid']||!$mcmod['name']||!$mcmod['description']||!$mcmod['version']||!$mcmod['mcversion']||!$mcmod['url']||!$mcmod['authorList']) {
                $warn['b'] = true;
                $warn['level'] = "info";
                $warn['message'] = "There is some information missing in mcmod.info.";
            }
        }
    } else { # is 1.14+ mod
        $legacy=false;
        $toml=new Toml;
        $mcmod = $toml->parse($result);

        // todo: technically it's possible to have multiple mods in one file, which the toml parser supports. however we (and the majority of others) don't support this.
        if (sizeof($mcmod['mods'])==0) {
            echo '{"status":"error","message":"Could not parse TOML->mods->0."}';
            // exit();
        }
        $mcmod['mods']=$mcmod['mods'][0]; 
        // ..and as we're supporting legacy mods, we're doing it this !@$%# way 
        // todo: don't do it this way.
        // this is because the toml spec says [[]] means an array. for us this means mods=>[{...}], aka
        // mods => [
        //    0 => {
        //      'modId'=>...
        // }
        // ]

        // file_put_contents('toml_output.json', JSON_ENCODE($mcmod), FILE_APPEND);

        if (! (array_key_exists('modId', $mcmod['mods'])
            && array_key_exists('version', $mcmod['mods'])
            && array_key_exists('displayName', $mcmod['mods'])
            && (array_key_exists('author', $mcmod['mods']) || array_key_exists('authors', $mcmod['mods']))
            && array_key_exists('description', $mcmod['mods'])
        )) {
            $warn['b'] = true;
            $warn['level'] = "info";
            $warn['message'] = "There is some information missing in mcmod.info.";
        }
    }

    if ($zipExists) { // while we could put a file check here, it'd be redundant (it's checked before).
        // cached zip
    } else {
        $zip = new ZipArchive();
        if ($zip->open($fileZipLocation, ZIPARCHIVE::CREATE) !== TRUE) {
            echo '{"status":"error","message":"Could not open archive"}';
            // exit();
        }
        $zip->addEmptyDir('mods');
        if (is_file($fileJarInFolderLocation)) {
            $zip->addFile($fileJarInFolderLocation, "mods/".$fileName) or die ('{"status":"error","message":"Could not add file $key"}');
        }
        $zip->close();
    }

    if ($legacy) {
        // legacy 1.14-
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
    } else {
        // 'modern' 1.14+
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
            require('mcVersionCompare.php');
            $querystring = "SELECT version,mcversion FROM mods WHERE type='forge' ORDER BY version ASC";
            $query = $db->query($querystring);
            if (sizeof($query)>0) { // if we even have any forges
                foreach ($query as $row) {
                    // file_put_contents("../status.log", "got a forge: ".JSON_ENCODE($row)."\n", FILE_APPEND);
                    if (mcVersionCompare($row['version'],$mcmod['loaderVersion'])) {
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

    if ($zipExists) {
        // cached zip, use given md5. (md5 is not checked empty(); should always be given if cached!)
    } else {
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
            echo '{"status":"succ","message":"Mod has been uploaded and saved.","modid":'.$db->insert_id().'}';
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
            //exit();
        } else {
            $fq = $db->query("SELECT `id` FROM `mods` WHERE `filename` = '".$fileNameZip."'");
            if (sizeof($fq)==1) {
                echo '{"status":"info","message":"This mod is already in the database.","modid":'.($fq)['id'].'}';
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
