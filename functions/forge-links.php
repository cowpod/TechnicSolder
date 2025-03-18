<?php
// this is patchwork in order to make up for missing links in the offical json file.
// should the links become available, or the format of their files change, this needs to be rewritten.

$forge_data = file_get_contents("https://files.minecraftforge.net/net/minecraftforge/forge/promotions_slim.json"); // can't find normal promotions.json
if (empty($forge_data)) {
    error_log("forge-links.php: couldn't get forge data");
    die("couldn't get forge data");
}

$forge_link = "https://maven.minecraftforge.net/net/minecraftforge/forge";
$versions = [];
$id = 0;

require_once("db.php");
$db=new Db;
$db->connect();

// this was called INSIDE the for loop before...
$forgesq = $db->query("SELECT `version` FROM `mods` WHERE `type` = 'forge'");
$forges_installed=[];
if($forgesq) {
    foreach($forgesq as $forge) {
        array_push($forges_installed, $forge['version']);
    }
}

$forges=[];
foreach (json_decode($forge_data, true)['promos'] as $gameVersion => $forgeVersion) { // key, value
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

header('Content-Type: application/json');
print_r(json_encode($forges, JSON_PRETTY_PRINT));
exit();