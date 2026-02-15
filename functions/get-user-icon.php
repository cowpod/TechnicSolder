<?php

session_start();
if (empty($_SESSION['user'])) {
    die("Unauthorized request or login session has expired!");
}


require_once("db.php");
$db = new Db();
$db->connect();

$geticon = $db->query("
    SELECT icon 
    FROM users 
    WHERE name = {$db->quote($_SESSION['user'])}
") ?: [];
$db->disconnect();

if (!$geticon) {
    die("Could not get user icon");
}

$data = base64_decode($geticon[0]['icon']);
$finfo = finfo_open();
$type = finfo_buffer($finfo, $data, FILEINFO_MIME_TYPE);
header("Content-Type: {$type}");
die($data);