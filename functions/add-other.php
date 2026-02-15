<?php

header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->files_upload()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

require('slugify.php');

$fileName = slugify2($_FILES["fiels"]["name"], '-'); // only allow \w\-\.
$fileTmpLoc = $_FILES["fiels"]["tmp_name"];

if (!$fileTmpLoc) {
    echo '{"status":"error","message":"File is too big! Check your post_max_size (current value '.ini_get('post_max_size').') and upload_max_filesize (current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}';
    exit();
}

if (str_ends_with($fileName, '.zip')) {
    // check file magic
    $filetype = mime_content_type($fileTmpLoc);
    if ($filetype != 'application/zip') {
        error_log('{"status":"error","message":"Not a ZIP file."}');
        die('{"status":"error","message":"Not a ZIP file."}');
    }
} else {
    error_log('{"status":"error","message":"Not a ZIP file."}');
    die('{"status":"error","message":"Not a ZIP file."}');
}

// unlike add-mod.php, we don't have any metadata to go off of.
// and as we don't create a zip, we can simply check the files' md5s.
// this means a user can upload multiple timestamp variations of the exact same file, in a zip.
// we will allow this.

$md5_file_tmp = md5_file($fileTmpLoc);

if (file_exists("../others/".$fileName)) {
    // immediately check if they're identical
    if (md5_file("../others/".$fileName) === $md5_file_tmp) {
        error_log('identical file');
        die('{"status":"error","message":"File already exists!"}');

    } else { // file exists, different content! gotta cycle through variations
        $counter = 1;
        $tmpName = str_replace('.zip', '-'.$counter.'.zip', $fileName);

        // while filename variation also exists and is different
        while (file_exists('../others/'.$tmpName) && md5_file('../others/'.$tmpName) !== $md5_file_tmp) {
            $counter += 1;
            $tmpName = str_replace('.zip', '-'.$counter.'.zip', $fileName);
        }

        // if we ended up finding an identical filename variation
        if (file_exists('../others/'.$tmpName) && md5_file('../others/'.$tmpName) === $md5_file_tmp) {
            error_log('identical file variation');
            die('{"status":"error","message":"File already exists!"}');
        }

        // settle on a filename variation
        $fileName = $tmpName;
    }
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}
require_once("db.php");
global $db;
if (empty($db)) {
    $db = new Db();
}
$db->connect();

if (!move_uploaded_file($fileTmpLoc, "../others/{$fileName}")) {
    die('{"status":"error","message":"Could not move file"}');
}

$pretty_name = $fileName;
$name = slugify($pretty_name);
$author = $_SESSION['name'];
$protocol = ($config->exists('protocol') && !empty($config->get('protocol'))) ? $config->get('protocol') : strtolower(current(explode('/', $_SERVER['SERVER_PROTOCOL'])))."://";
$url = $protocol.$config->get('host').$config->get('dir')."others/".$fileName;
$md5 = md5_file("../others/".$fileName);
$file_size = filesize("../others/".$fileName);

if (!$db->execute("
    INSERT INTO mods (
        name,
        pretty_name,
        md5,
        url,
        author,
        description,
        filename,
        filesize,
        type,
        version,
        mcversion
    ) VALUES (
        {$db->quote($name)},
        {$db->quote($pretty_name)},
        {$db->quote($md5)},
        {$db->quote($url)},
        {$db->quote($author)},
        'Custom file',
        {$db->quote($fileName)},
        {$file_size},
        'other',
        '1.0',
        '*'
    )
")) {
    die('{"status":"error","message":"File could not be added to database"}');
}

die('{"status":"succ","message":"File has been saved."}');