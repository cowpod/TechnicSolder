<?php
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user'])) {
    die('{}');
}

require_once('sanitize.php');

if (empty($_GET['link'])) {
	die('{}');
}

if (!filter_var($_GET['link'], FILTER_VALIDATE_URL)){
	error_log("resolder.php: bad link.");
	die('{}');
}

error_log("resolder.php: getting ANY url '{$_GET['link']}'");

$response = @file_get_contents($_GET['link']);

if ($response) {
	error_log("resolder.php: could not download data: '{$_GET['link']}'");
	die('{}');
}
die($response);
