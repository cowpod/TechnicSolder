<?php
session_start();

if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

if (empty($_POST['db-type'])) {
    die('{"status":"error","message":"Missing db-type"}');
}
if (empty($_POST['db-host'])) {
    die('{"status":"error","message":"Missing db-host"}');
}
if (empty($_POST['db-user'])) {
    die('{"status":"error","message":"Missing db-user"}');
}
if (empty($_POST['db-pass'])) {
    die('{"status":"error","message":"Missing db-pass"}');
}

require_once("db.php");
$db = new Db();

if ($db->test($_POST['db-type'], $_POST['db-host'], $_POST['db-user'], $_POST['db-pass'], $_POST['db-name'])) {
    die('{"status":"succ","message":"Connected to database"}');
} else {
    die('{"status":"error","message":"Could not connect to database"}');
}
