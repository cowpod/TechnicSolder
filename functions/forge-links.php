<?php

$forge_data = file_get_contents("https://files.minecraftforge.net/net/minecraftforge/forge/promotions_slim.json"); // can't find normal promotions.json
if (empty($forge_data)) {
    error_log("forge-links.php: couldn't get forge data");
    die("couldn't get forge data");
}

$forge_link = "https://maven.minecraftforge.net/net/minecraftforge/forge";
$versions = [];
$forges = [];
$id = 0;

require("db.php");
$db=new Db;
$db->connect();

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

        $modsq = $db->query("SELECT * FROM `mods` WHERE `name` = 'forge' AND `version` = '".$forgeVersion."'");
        if (sizeof($modsq)==0) {
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
