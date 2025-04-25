<?php
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('sanitize.php');

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->privileged()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

require_once('./configuration.php');
$config = new Config();

if (isset($_POST['enable_self_updater'])) {
    $config->set('enable_self_updater','on');
} else {
    $config->set('enable_self_updater','off');
}
if (isset($_POST['dev_builds'])) {
    $config->set('dev_builds','on');
} else {
    $config->set('dev_builds','off');
}
if (isset($_POST['use_verifier'])) {
    $config->set('use_verifier','on');
} else {
    $config->set('use_verifier','off');
}
if (isset($_POST['modrinth_integration'])) {
    $config->set('modrinth_integration','on');
} else {
    $config->set('modrinth_integration','off');
}
if (isset($_POST['forge_integration'])) {
    $config->set('forge_integration','on');
} else {
    $config->set('forge_integration','off');
}
if (isset($_POST['neoforge_integration'])) {
    $config->set('neoforge_integration','on');
} else {
    $config->set('neoforge_integration','off');
}
if (isset($_POST['fabric_integration'])) {
    $config->set('fabric_integration','on');
} else {
    $config->set('fabric_integration','off');
}

die('{"status":"succ","message":"Server settings saved."}');