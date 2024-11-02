<?php
header('Content-Type: application/json');

$url = $_SERVER['REQUEST_URI'];

// remove args
if (strpos($url, '?') !== FALSE) {
    $url = substr($url, 0, strpos($url, "?"));
}
// if trailing / and not api/, remove trailing / and append query string if present.
if (substr($url, -1)=="/" && substr($url, -4)!=="api/") {
    if (!empty($_SERVER['QUERY_STRING'])) {
        header("Location: " . rtrim($url, '/') . "?" . $_SERVER['QUERY_STRING']);
    } else {
        header("Location: " . rtrim($url, '/'));
    }
}

$config = require("../functions/config.php");

if(str_ends_with($url, "api/")){
	die('{"api":"Solder.cf","version":"v1.4.0","stream":"Release"}'); // todo: this is Dev not release, but may break things if changed...
} 
if (str_ends_with($url, "api/verify")) {
    die('{"error":"No API key provided."}');
}
if (str_ends_with($url, "api/verify/".substr($url, strrpos($url, '/') + 1))) {
    if (substr($url, strrpos($url, '/') + 1)==$config['api_key']) { // todo: actual validation?
        die('{"valid":"Key validated.","name":"API KEY","created_at":"A long time ago"}');
    }
    die('{"error":"Invalid key provided."}');
}

$PROTO_STR = strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL']))).'://';

require_once("../functions/db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

if (isset($_GET['cid'])) {
    $client_uuid=$_GET['cid'];
} else {
    $client_uuid='';
}
if (isset($_GET['k'])) {
    $client_api_key=$_GET['k'];
} else {
    $client_api_key='';
}


if (preg_match("/api\/modpack$/", $url)) { // modpacks
    // $modpacksq = $db->query("SELECT * FROM `modpacks`");
    $modpacksq = $db->query("
    SELECT M.*, 
        B.name AS latest_name, 
        B2.name AS recommended_name
    FROM modpacks AS M 
    LEFT JOIN builds AS B 
        ON M.latest = B.id 
    LEFT JOIN builds AS B2 
        ON M.recommended = B2.id;
    ");
    $modpacks = [];
    if (isset($_GET['include']) && $_GET['include'] == "full") {

        foreach($modpacksq as $modpack) {
            $builds = [];
            $counter=0;
            $buildsq = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']);

            foreach($buildsq as $build) {
                $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$build['clients'].")");
                if ($build['public']==1 || $clientsq || $_GET['k']==$config['api_key']) {
                    $builds[$counter]=$build['name'];
                    $counter++;
                }
            }
            $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$modpack['clients'].")");
            if ($modpack['public']==1 || $clientsq || $client_api_key==$config['api_key']) {
                $modpacks[$modpack['name']] = [
                    "name" => $modpack['name'],
                    "display_name" => $modpack['display_name'],
                    "url" => $modpack['url'],
                    "icon" => $modpack['icon'],
                    "icon_md5" => $modpack['icon_md5'],
                    "logo" => $modpack['logo'],
                    "logo_md5" => $modpack['logo_md5'],
                    "background" => $modpack['background'],
                    "background_md5" => $modpack['background_md5'],
                    "recommended" => $modpack['recommended_name'],
                    "latest" => $modpack['latest_name'], 
                    "builds" => $builds
                ];
            }
        }
    } else {
        foreach($modpacksq as $modpack) {
            $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$modpack['clients'].")");
            if ($modpack['public']==1 || $clientsq || $client_api_key==$config['api_key']) {
                $mn = $modpack['name'];
                $mpn = $modpack['display_name'];
                $modpacks[$mn] = $mpn;
            }
        }
    }
    die(json_encode(["modpacks"=>$modpacks, "mirror_url"=>"http://".$config['host']."/mods"]));
} 
elseif (preg_match("/api\/modpack\/([a-z\-|0-9]+)$/", $url, $matches)) { // modpack details
    $uri_modpack = $matches[1];

    // $modpackq = $db->query("SELECT * FROM `modpacks` WHERE name='".$db->sanitize($uri_modpack)."'");
    $modpackq = $db->query("
        SELECT M.*, 
            B.name AS latest_name, 
            B2.name AS recommended_name
        FROM modpacks AS M 
        LEFT JOIN builds AS B 
            ON M.latest = B.id 
        LEFT JOIN builds AS B2 
            ON M.recommended = B2.id
        WHERE M.name = '".$db->sanitize($uri_modpack)."';
    ");
    if (!$modpackq) {
        die('{"error":"Modpack does not exist"}');
    }

    foreach($modpackq as $modpack) { // sql col `name` isn't guaranteed to be unique...
        $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$modpack['clients'].")");
        if ($modpack['public']==1 || $clientsq || $client_api_key==$config['api_key']) {
            $builds = [];
            $counter=0;
            $buildsq = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']);

            foreach($buildsq as $build) {
                $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$build['clients'].")");
                if ($build['public']==1 || $clientsq || $client_api_key==$config['api_key']) {
                    $builds[$counter]=$build['name'];
                    $counter++;
                }
            }
            die(json_encode([
                "name" => $modpack['name'],
                "display_name" => $modpack['display_name'],
                "url" => $modpack['url'],
                "icon" => $modpack['icon'],
                "icon_md5" => $modpack['icon_md5'],
                "logo" => $modpack['logo'],
                "logo_md5" => $modpack['logo_md5'],
                "background" => $modpack['background'],
                "background_md5" => $modpack['background_md5'],
                "recommended" => $modpack['recommended_name'],
                "latest" => $modpack['latest_name'],
                "builds" => $builds
            ]));
        } else {
            die('{"error":"This modpack is private."}');
        }
    }
// } else { // build details (including mods)
} elseif (preg_match("/api\/modpack\/([a-z\-|0-9]+)\/?([a-z\-|0-9]+)?$/", $url, $matches)) { // modpack details
    $uri_modpack = $matches[1];
    $uri_build = $matches[2];
    // could use a join on/using for better speed.
    $modpacksq = $db->query("SELECT * FROM `modpacks` WHERE name='".$db->sanitize($uri_modpack)."'");
    if (!$modpacksq) {
        die('{"status":404,"error":"Not Found"}');
    }

    foreach($modpacksq as $modpack) {
        $buildsq = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']." AND name='".$db->sanitize($uri_build)."'");

        foreach($buildsq as $build) {
            $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$build['clients'].")");
            if ($build['public']==1 || $clientsq || $client_api_key==$config['api_key']) {
                $mods = [];
                $modslist = explode(',', $build['mods']);
                $modnumber = 0;

                foreach($modslist as $modid) {
                    if (empty($modid)) {
                        error_log("API: double-comma (malformed 'mods' column) in database, skipping");
                        continue;
                    }
                    $modq = $db->query("SELECT * FROM mods WHERE id='".$modid."'");
                    if (!$modq || sizeof($modq)!=1) {
                        error_log("API: failed to get mod for id='".$mod."', skipping");
                        continue;
                    }
                    $mod = $modq[0];
                    if (empty($mod)) {
                        error_log("API: missing ALL data for mod id='".$modid."', skipping");
                        continue;
                    }
                    if (empty($mod['name']) || empty($mod['type']) || empty($mod['version'])) { // todo: add more checks?
                        error_log("API: missing critical data for mod id='".$modid."', skipping");
                        continue;
                    }
                    if (isset($_GET['include']) && $_GET['include']=="mods") {
                        $mods[$modnumber] = [
                            "name" => $mod['name'],
                            "version" => $mod['version'],
                            "md5" => $mod['md5'],
                            "url" => !empty($mod['url']) ? $mod['url'] : $PROTO_STR.$config['host'].$config['dir'].$mod['type']."s/".$mod['filename'],
                            "pretty_name" => $mod['pretty_name'],
                            "author" => $mod['author'],
                            "description" => $mod['description'],
                            "link" => $mod['link'],
                            "donate" => $mod['donlink']
                        ];
                    } else {
                        $mods[$modnumber] = [
                            "name" => $mod['name'],
                            "version" => $mod['version'],
                            "md5" => $mod['md5'],
                            "url" => !empty($mod['url']) ? $mod['url'] : $PROTO_STR.$config['host'].$config['dir'].$mod['type']."s/".$mod['filename']
                        ];
                    }
                    $modnumber++;
                }
                die(json_encode([
                    "minecraft" => str_replace("f", "", $build['minecraft']),
                    "java" => $build['java'],
                    "memory" => $build['memory'],
                    "forge" => null, // todo: is this a bool? or a forge version? or are there more keys for fabric/etc?
                    "mods" => $mods,
                ]));
            } else {
                die('{"error":"\n\rThis build is private. \n\rPlease contact '.$config['author'].' for more information."}');
            }
        }
        die('{"error":"Build does not exist"}');
    }
}