<?php

session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}

require_once('sanitize.php');

require_once("db.php");
$db = new Db();
$db->connect();

$geticon = $db->query("SELECT icon FROM users WHERE name = '{$_SESSION['user']}'");
$db->disconnect();

if ($geticon && !empty($geticon[0]['icon'])) {
    $data = base64_decode($geticon[0]['icon']);
    $f = finfo_open();
    $type = finfo_buffer($f, $data, FILEINFO_MIME_TYPE);
    $etag = md5($data);
    // skip caching since only an authorized user can get their own image.
    // header_remove("Pragma");
    // header("Cache-Control: public, max-age=3600");
    // header("Expires: " . gmdate("D, d M Y H:i:s", time() + 3600) . " GMT");
    // header("ETag: \"$etag\"");
    header("Content-Type: {$type}");
    die($data);
} else {
    error_log("get-user-icon.php: could not get user icon from db");
}
