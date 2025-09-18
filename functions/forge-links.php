<?php

header('Content-Type: application/json');
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('sanitize.php');

$forge_data = @file_get_contents("https://files.minecraftforge.net/net/minecraftforge/forge/promotions_slim.json"); // can't find normal promotions.json
if ($forge_data === false) {
    error_log("forge-links.php: couldn't get forge data");
    die("couldn't get forge data");
}

$forge_link = "https://maven.minecraftforge.net/net/minecraftforge/forge";
$versions = [];
$id = 0;

require_once("db.php");
$db = new Db();
$db->connect();

// this was called INSIDE the for loop before...
$forgesq = $db->query("SELECT `version` FROM `mods` WHERE `type` = 'forge'");
$forges_installed = [];
if ($forgesq) {
    foreach ($forgesq as $forge) {
        array_push($forges_installed, $forge['version']);
    }
}

$forges = [];
$decode = @json_decode($forge_data, true);
if ($decode === null || empty($decode['promos'])) {
    die('{"status":"error","message":"Could not decode forge data"}');
}

foreach ($decode['promos'] as $gameVersion => $forgeVersion) { // key, value
    $id++;
    if (strpos($gameVersion, "latest")) {
        $gameVersion = str_replace("-latest", "", $gameVersion);
        // 1.13 "1.13.2-25.0.219" and later don't have a universal.jar
        if (version_compare($gameVersion, '1.13', '>=')) {
            $suffixExt = 'installer.jar';
        } else {
            $suffixExt = 'universal.jar';
        }
        $versions[$forgeVersion] = $forge_link.'/'.$gameVersion.'-'.$forgeVersion.'/forge-'.$gameVersion.'-'.$forgeVersion.'-'.$suffixExt;

        if (!in_array($forgeVersion, $forges_installed)) {
            $forges[$gameVersion] = array(
                "id" => $id,
                "mc" => $gameVersion,
                "name" => $forgeVersion,
                "link" => $versions[$forgeVersion]
            );
        }
    }
}
$encoded = @json_encode($forges);
if ($encoded === false) {
    error_log('forge-links.php: could not encode forges to json');
}

die($encoded);
