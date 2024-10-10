<?php
session_start();
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die('Unathorized request or login session has expired!');
}
if (!$_GET['id']) {
    die('ID not provided');
}
$config = require("config.php");

require_once("db.php");
$db=new Db;
$db->connect();

define('STREAM_CHUNK_SIZE', 1024*64); // 64KB
define('MAX_DOWNLOAD_TIME', 60*20); // 20 minutes
// define('ASSUMED_USER_TRANSFER_SPEED', 1024*1024); // 1MB/s

$filenameq = $db->query("SELECT `filename` FROM `mods` WHERE `id` = ".$db->sanitize($_GET['id']));
if ($filenameq) {
    assert(sizeof($q)==1);
    $filenameq=$filenameq[0];
}
$fileName = $filenameq['filename'];
$file_location ='../mods/'.$fileName;

// todo: ensure path is safe

$zip = new ZipArchive;
$zippedJarFilePath='';
$zippedJarFileSize=0;

if ($zip->open($file_location)===TRUE) {
    $totalFiles = $zip->numFiles;
    // assert($totalFiles==2); 
    for ($i = 0; $i < $totalFiles; $i++) {
        $zippedJarFileName = $zip->getNameIndex($i);
        if (str_ends_with($zippedJarFileName, '.jar')) {
            $zippedJarFilePath=$zippedJarFileName;
            $zippedJarFileSize=$zip->statIndex($i)['size'];
        }
    }
}

set_time_limit(MAX_DOWNLOAD_TIME); //$zippedJarFileSize/ASSUMED_USER_TRANSFER_SPEED);

header('Content-Type: application/octet-stream');
header("Content-Length:".$zippedJarFileSize);
header("Content-Disposition: attachment; filename=".basename($zippedJarFilePath));

$content_stream = $zip->getStream($zippedJarFilePath);

while (!feof($content_stream)) {
    $buffer = fread($content_stream, STREAM_CHUNK_SIZE);
    echo $buffer;
    flush();
}

fclose($content_stream);
$zip->close();

exit();