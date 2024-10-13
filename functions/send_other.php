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


$fileName = $_FILES["fiels"]["name"];
$fileTmpLoc = $_FILES["fiels"]["tmp_name"];
if (!$fileTmpLoc) {
    echo '{"status":"error","message":"File is too big! Check your post_max_size (current value '.ini_get('post_max_size').') and upload_max_filesize (current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}';
    exit();
}

if (str_ends_with($fileName, '.zip')) {
    // check file magic
    $filetype=mime_content_type($fileTmpLoc);
    if ($filetype!='application/zip') {
        error_log ('{"status":"error","message":"Not a ZIP file."}');
        die ('{"status":"error","message":"Not a ZIP file."}');
    }
} else {
    error_log ('{"status":"error","message":"Not a ZIP file."}');
    die ('{"status":"error","message":"Not a ZIP file."}');
}

// unlike send_mods.php, we don't have any metadata to go off of. 
// and as we don't create a zip, we can simply check the files' md5s.
// this means a user can upload multiple timestamp variations of the exact same file, in a zip.
// we will allow this.

$md5_file_tmp=md5_file($fileTmpLoc);

if (file_exists("../others/".$fileName)) {
    // immediately check if they're identical
    if (md5_file("../others/".$fileName)===$md5_file_tmp) {
        error_log('identical file');
        die ('{"status":"error","message":"File already exists!"}');

    } else { // file exists, different content! gotta cycle through variations
        $counter = 1;
        $tmpName = str_replace('.zip', '-'.$counter.'.zip', $fileName);

        // while filename variation also exists and is different
        while (file_exists('../others/'.$tmpName) && md5_file('../others/'.$tmpName)!==$md5_file_tmp) {
            $counter += 1;
            $tmpName = str_replace('.zip', '-'.$counter.'.zip', $fileName);
        }

        // if we ended up finding an identical filename variation
        if (file_exists('../others/'.$tmpName) && md5_file('../others/'.$tmpName)===$md5_file_tmp) {
            error_log('identical file variation');
            die ('{"status":"error","message":"File already exists!"}');
        }

        // settle on a filename variation
        $fileName = $tmpName;
    }
}

require('slugify.php');

$config = require("config.php");
require_once("db.php");
$db=new Db;

if (move_uploaded_file($fileTmpLoc, "../others/".$fileName)) {
    $db->connect();
    
    $pretty_name = $db->sanitize($fileName);
    $name = slugify($pretty_name);
    $author = $config['author'];
    $url = strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL'])))."://".$config['host'].$config['dir']."others/".$fileName;
    $md5 = md5_file("../others/".$fileName);

    $res = $db->execute("INSERT INTO `mods` (`name`,`pretty_name`,`md5`,`url`,`author`,`description`,`filename`,`type`) VALUES ('".$name."','".$pretty_name."','".$md5."','".$url."','".$author."','Custom file by ".$author."','".$fileName."','other')");

    $db->disconnect();

    if ($res) {
        echo '{"status":"succ","message":"File has been saved."}';
    } else {
        echo '{"status":"error","message":"File could not be added to database"}';
    }
} else {
    echo '{"status":"error","message":"Permission denied! Open SSH and run chown -R www-data '.dirname(dirname(get_included_files()[0])).'"}';
}

exit();
?>
