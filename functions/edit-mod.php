<?php
session_start();
header("Content-Type: application/json");

if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->mods_edit()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

// required vars
if (empty($_POST['id']) && empty($_POST['name'])) {
    die('{"status":"error","message":"One of id or name must be specified"}');
} else if (!empty($_POST['id']) && !empty($_POST['name'])) {
    die('{"status":"error","message":"Only one of id or name can be specified"}');
}
elseif (!empty($_POST['id']) && !is_numeric($_POST['id'])) {
    die('{"status":"error","message":"Malformed id"}');
}
elseif (!empty($_POST['name']) && !preg_match('/^[\w\-]+$/',$_POST['name'])) {
    die('{"status":"error","message":"Malformed name"}');
}

// optional vars
// we can't sanitize these. have to quite or -
// todo: prepared statements
// if (array_key_exists('pretty_name',$_POST)) { 
//     die('{"status":"error","message":"Malformed pretty name"}');
// }
// if (array_key_exists('description',$_POST)) { 
//     die('{"status":"error","message":"Malformed description"}');
// }
if (array_key_exists('author',$_POST) && !empty($_POST['author']) && !preg_match('/^[\w\s\.\-,]+$/',$_POST['author'])) {
    die('{"status":"error","message":"Malformed author"}');
}
if (array_key_exists('version', $_POST) && !empty($_POST['version']) && !preg_match('/^[\w\-\.+]+$/',$_POST['version'])) {
    die('{"status":"error","message":"Malformed version"}');
}
if (array_key_exists('mcversion',$_POST) && !empty($_POST['mcversion']) && !preg_match('/^[\w\-\.+,\s\[\]\(\)]+$/',$_POST['mcversion'])) {
    die('{"status":"error","message":"Malformed mcversion"}');
}
if (array_key_exists('link',$_POST) && !empty($_POST['link']) && !filter_var($_POST['link'],FILTER_VALIDATE_URL)) {
    die('{"status":"error","message":"Malformed link"}');
}
if (array_key_exists('donlink',$_POST) && !empty($_POST['donlink']) && !filter_var($_POST['donlink'], FILTER_VALIDATE_URL)) {
    die('{"status":"error","message":"Malformed donlink"}');
}
if (array_key_exists('url',$_POST) && !empty($_POST['url']) && !filter_var($_POST['url'],FILTER_VALIDATE_URL)) {
    die('{"status":"error","message":"Malformed url"}');
}
if (array_key_exists('md5',$_POST) && !empty($_POST['md5']) && !ctype_alnum($_POST['md5'])) {
    die('{"status":"error","message":"Malformed md5"}');
}
if (array_key_exists('loadertype',$_POST) && !empty($_POST['loadertype']) && !ctype_alpha($_POST['loadertype'])) {
    die('{"status":"error","message":"Malformed loadertype"}');
}

global $db;
require_once("db.php");
if (!isset($db)) {
    $db = new Db();
    $db->connect();
}

// build massive sql statement
$sql = 'UPDATE mods SET';
if (array_key_exists('pretty_name',$_POST) && !is_null($_POST['pretty_name'])) {
    $sql .= " pretty_name = {$db->quote($_POST['pretty_name'])},";
}
if (array_key_exists('description',$_POST) && !is_null($_POST['description'])) {
    $sql .= " description = {$db->quote($_POST['description'])},";
}
if (array_key_exists('author',$_POST) && !is_null($_POST['author'])) {
    $sql .= " author = {$db->quote($_POST['author'])},";
}
if (array_key_exists('version', $_POST) && !is_null($_POST['version'])) {
    $sql .= " version = {$db->quote($_POST['version'])},";
}
if (array_key_exists('mcversion',$_POST) && !is_null($_POST['mcversion'])) {
    $sql .= " mcversion = {$db->quote($_POST['mcversion'])},";
}
if (array_key_exists('link',$_POST) && !is_null($_POST['link'])) {
    $sql .= " link = {$db->quote($_POST['link'])},";
}
if (array_key_exists('donlink',$_POST) && !is_null($_POST['donlink'])) {
    $sql .= " donlink = {$db->quote($_POST['donlink'])},";
}
if (array_key_exists('url',$_POST) && !is_null($_POST['url'])) {
    $sql .= " url = {$db->quote($_POST['url'])},";
}
if (array_key_exists('md5',$_POST) && !is_null($_POST['md5'])) {
    $sql .= " md5 = {$db->quote($_POST['md5'])},";
}
if (array_key_exists('loadertype',$_POST) && !is_null($_POST['loadertype'])) {
    $sql .= " loadertype = {$db->quote($_POST['loadertype'])},";
}
$sql = rtrim($sql, ',');

if (!empty($_POST['id'])) {
    $sql .= " WHERE id = {$_POST['id']}";
} else {
    $sql .= " WHERE name = {$db->quote($_POST['name'])}";
} // else: we already check that one exists

if (!$db->execute($sql)) {
    die('{"status":"error","message":"Could not update mod"}');
}

die('{"status":"succ","message":"Mod updated"}');

