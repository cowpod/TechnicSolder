<?php
// this is patchwork in order to make up for missing links in the offical json file.
// should the links become available, or the format of their files change, this needs to be rewritten.

$forge_json = file_get_contents("https://files.minecraftforge.net/net/minecraftforge/forge/promotions_slim.json"); // can't find normal promotions.json
if (empty($forge_json)) {
    error_log("forge-links.php: couldn't get forge data");
    die("couldn't get forge data");
}

$forge_link = "https://maven.minecraftforge.net/net/minecraftforge/forge";
$versions = [];
$forges = [];
$id = 0;

require_once("db.php");
$db=new Db;
$db->connect();

$forges=[];
$forge_data=json_decode($forge_json, true)['promos'];

foreach ($forge_data as $gameVersion => $forgeVersion) { // key, value
    $id++;

    if (str_contains($gameVersion, "latest")) {
        $gameVersion = str_replace("-latest", "", $gameVersion);
        // 1.13 "1.13.2-25.0.219" and later don't have a universal.jar
        if (version_compare($gameVersion, '1.13', '>=')) {
            $suffixExt = 'installer.jar';
        } else {
            $suffixExt = 'universal.jar';
        }

        $versions[$forgeVersion] = $forge_link.'/'.$gameVersion.'-'.$forgeVersion.'/forge-'.$gameVersion.'-'.$forgeVersion.'-'.$suffixExt;

        if (!in_array($forgeVersion, $forges)) {
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