<?php

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    die('{}');
}

require_once('sanitize.php');

if (!isset($_GET['slug']) || !isset($_GET['build'])) {
    die("{}");
}
if (!preg_match('/[\w\-]+/', $_GET['slug']) || !preg_match('/[\w\-\.]+/', $_GET['build'])) {
    die("{}");
}
$url = "https://api.technicpack.net/modpack/{$_GET['slug']}?build={$_GET['build']}";
$data = @file_get_contents($url);
if ($data === false) {
    error_log("platform.php: could not get technic api data: '{$url}'");
    die('{}');
}
die($data);
