<?php
header('Content-Type: application/json');

$url = $_SERVER['REQUEST_URI'];
$PROTO_STR = strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL']))).'://';

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

require_once("../functions/db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}
<<<<<<< HEAD
if (uri($url, "api/")) {
    print '{"api":"Solder.cf","version":"v1.3.4","stream":"Dev"}';
    exit();
}
if (uri($url, "api/verify")) {
    print '{"error":"No API key provided."}';
    exit();
}
if (uri($url, "api/verify/".substr($url, strrpos($url, '/') + 1))) {
    if (substr($url, strrpos($url, '/') + 1)==$config['api_key']) {
        print '{"valid":"Key validated.","name":"API KEY","created_at":"A long time ago"}';
        exit();
    }
    print '{"error":"Invalid key provided."}';
    exit();
}
if (uri($url, "api/modpack")) {
    if (isset($_GET['include'])) {
        if ($_GET['include'] == "full") {
            $result = mysqli_query($conn, "SELECT * FROM `modpacks`");
            $modpacks = array();
            while ($modpack=mysqli_fetch_array($result)) {
                $buildsres = mysqli_query($conn, "SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']);
                $builds = [];
                while ($build=mysqli_fetch_array($buildsres)) {
                    $clients = [];
                    $clientsq = mysqli_query($conn, "SELECT * FROM `clients` WHERE `id` IN (".$build['clients'].")");
                    while ($client=mysqli_fetch_array($clientsq)) {
                        array_push($clients, $client['UUID']);
                    }
                    if ($build['public']==1||in_array($_GET['cid'], $clients)||$_GET['k']==$config['api_key']) {
                        array_push($builds, $build['name']);
                    }
                }
                $clients = [];
                $clientsq = mysqli_query($conn, "SELECT * FROM `clients` WHERE `id` IN (".$modpack['clients'].")");
                while ($client=mysqli_fetch_array($clientsq)) {
                    array_push($clients, $client['UUID']);
                }
                if ($modpack['public']==1||in_array($_GET['cid'], $clients)||$_GET['k']==$config['api_key']) {
                    $modpacks[$modpack['name']] = array(
                        "name" => $modpack['name'],
                        "display_name" => $modpack['display_name'],
                        "url" => $modpack['url'],
                        "icon" => $modpack['icon'],
                        "icon_md5" => $modpack['icon_md5'],
                        "logo" => $modpack['logo'],
                        "logo_md5" => $modpack['logo_md5'],
                        "background" => $modpack['background'],
                        "background_md5" => $modpack['background_md5'],
                        "recommended" => $modpack['recommended'],
                        "latest" => $modpack['latest'],
                        "builds" => $builds
                    );
                }
                $response = array(
                    "modpacks" => $modpacks,
                    "mirror_url" => "http://".$config['host']."/mods"
                );
            }
        } else {
            $modpacks = array();
            $result = mysqli_query($conn, "SELECT * FROM `modpacks`");
            while ($modpack=mysqli_fetch_array($result)) {
                $clients = [];
                $clientsq = mysqli_query($conn, "SELECT * FROM `clients` WHERE `id` IN (".$modpack['clients'].")");
                while ($client=mysqli_fetch_array($clientsq)) {
                    array_push($clients, $client['UUID']);
                }
                if ($modpack['public']==1||in_array($_GET['cid'], $clients)||$_GET['k']==$config['api_key']) {
                    $mn = $modpack['name'];
                    $mpn = $modpack['display_name'];
                    $modpacks[$mn] = $mpn;
                }
            }
            $response = array(
                "modpacks" => $modpacks,
                "mirror_url" => "http://".$config['host']."/mods"
            );
        }
    } else {
        $modpacks = array();
        $result = mysqli_query($conn, "SELECT * FROM `modpacks`");
        while ($modpack=mysqli_fetch_array($result)) {
            $clients = [];
            $clientsq = mysqli_query($conn, "SELECT * FROM `clients` WHERE `id` IN (".$modpack['clients'].")");
            while ($client=mysqli_fetch_array($clientsq)) {
                array_push($clients, $client['UUID']);
            }
            if ($modpack['public']==1||in_array($_GET['cid'], $clients)||$_GET['k']==$config['api_key']) {
                $mn = $modpack['name'];
                $mpn = $modpack['display_name'];
                $modpacks[$mn] = $mpn;
            }
=======

if(str_ends_with($url, "api/")){
	die('{"api":"Solder.cf","version":"v1.4.0","stream":"Release"}'); // todo: this is Dev not release, but may break things if changed...
} 
if (str_ends_with($url, "api/verify")) {
    die('{"error":"No API key provided."}');
}

$server_wide_api_key='';
if (!empty($config['api_key'])) {
    $server_wide_api_key=$config['api_key'];
}

if (preg_match("/api\/verify\/([a-zA-Z0-9]+)$/", $url, $matches)) {
    $client_api_key = $matches[1];

    if ($server_wide_api_key) {
        if ($client_api_key==$server_wide_api_key) {
            die('{"valid":"Key validated.","name":"API KEY","created_at":"A long time ago"}');
        }
    } else {
        // query db. multiple users could use the same technic api key...
        $apikeysq = $db->query("SELECT 1 FROM users WHERE api_key='{$client_api_key}'");
        if ($apikeysq) {
            die('{"valid":"Key validated.","name":"API KEY","created_at":"A long time ago"}');
>>>>>>> master
        }
    }
    
    die('{"error":"Invalid key provided."}');
}
<<<<<<< HEAD
if (uri($url, "api/modpack/".substr($url, strrpos($url, '/') + 1))) {
    $result = mysqli_query($conn, "SELECT * FROM `modpacks`");
    while ($modpack=mysqli_fetch_array($result)) {
        if (uri($url, "api/modpack/".$modpack['name'])) {
            $clients = [];
            $clientsq = mysqli_query($conn, "SELECT * FROM `clients` WHERE `id` IN (".$modpack['clients'].")");
            while ($client=mysqli_fetch_array($clientsq)) {
                array_push($clients, $client['UUID']);
            }
            if ($modpack['public']==1||in_array($_GET['cid'], $clients)||$_GET['k']==$config['api_key']) {
                $buildsres = mysqli_query($conn, "SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']);
                $builds = [];
                while ($build=mysqli_fetch_array($buildsres)) {
                    $clients = [];
                    $clientsq = mysqli_query($conn, "SELECT * FROM `clients` WHERE `id` IN (".$build['clients'].")");
                    while ($client=mysqli_fetch_array($clientsq)) {
                        array_push($clients, $client['UUID']);
                    }
                    if ($build['public']==1||in_array($_GET['cid'], $clients)||$_GET['k']==$config['api_key']) {
                        array_push($builds, $build['name']);
                    }
=======

$client_uuid = isset($_GET['cid']) ? $_GET['cid'] : '';

$valid_client_key = FALSE;
if (isset($_GET['k'])) {
    if ($server_wide_api_key) {
        if ($_GET['k']==$server_wide_api_key) {
            $valid_client_key = TRUE;
        }
    } else {
        $qk = $db->query("SELECT 1 FROM users WHERE api_key = '".$db->sanitize($_GET['k'])."'");
        if ($qk) {
            $valid_client_key = TRUE;
        } 
    }
}
if (preg_match("/api\/mod$/", $url)) {
    $modslist=[];
    if (!empty($_GET['loadertype']) && ctype_alnum($_GET['loadertype'])) {
        $loadertype=$_GET['loadertype'];
    }
    if (!empty($_GET['mcversion']) && preg_match('/^[a-zA-Z0-9\-\.]+$/', $_GET['mcversion'])) {
        $mcversion=$_GET['mcversion'];
    }
    if (!empty($loadertype) && !empty($mcversion)) {
        $modq = $db->query("SELECT * FROM mods WHERE type='mod' AND loadertype='{$loadertype}' AND ('{$mcversion}' LIKE mcversion || '%' OR mcversion LIKE '{$mcversion}' || '%')");
    } elseif (!empty($loadertype)) {
        $modq = $db->query("SELECT * FROM mods WHERE type='mod' AND loadertype='{$loadertype}'");
    } elseif (!empty($mcversion)) {
        $modq = $db->query("SELECT * FROM mods WHERE type='mod' AND '{$mcversion}' LIKE mcversion || '%'");
    } else {
        $modq = $db->query("SELECT * FROM mods WHERE type='mod'");
    }
    if ($modq) {
        foreach ($modq as $mod) {
            $filesize = isset($mod['filesize']) ? $mod['filesize'] : 0;
            $modentry = [
                'id'=>$mod['id'],
                'pretty_name'=>$mod['pretty_name'],
                'name'=>$mod['name'], 
                'version'=>$mod['version'], 
                'mcversion'=>$mod['mcversion'],
                'md5'=>$mod['md5'], 
                'url'=>$mod['url'], 
                'filesize'=>$filesize
            ];

            array_push($modslist, $modentry);
        }
    }
    echo json_encode($modslist, JSON_UNESCAPED_SLASHES);
}
else if (preg_match("/api\/loader$/", $url)) {
    $modslist=[];
    if (!empty($_GET['loadertype']) && ctype_alnum($_GET['loadertype'])) {
        $loadertype=$_GET['loadertype'];
    }
    if (!empty($_GET['mcversion']) && preg_match('/^[a-zA-Z0-9\-\.]+$/', $_GET['mcversion'])) {
        $mcversion=$_GET['mcversion'];
    }
    if (!empty($loadertype) && !empty($mcversion)) {
        $modq = $db->query("SELECT * FROM mods WHERE type='forge' AND loadertype='{$loadertype}' AND ('{$mcversion}' LIKE mcversion || '%' OR mcversion LIKE '{$mcversion}' || '%')");
    } elseif (!empty($loadertype)) {
        $modq = $db->query("SELECT * FROM mods WHERE type='forge' AND loadertype='{$loadertype}'");
    } elseif (!empty($mcversion)) {
        $modq = $db->query("SELECT * FROM mods WHERE type='forge' AND '{$mcversion}' LIKE mcversion || '%'");
    } else {
        $modq = $db->query("SELECT * FROM mods WHERE type='forge'");
    }
    if ($modq) {
        foreach ($modq as $mod) {
            $filesize = isset($mod['filesize']) ? $mod['filesize'] : 0;
            $modentry = [
                'id'=>$mod['id'],
                'pretty_name'=>$mod['pretty_name'],
                'name'=>$mod['name'], 
                'version'=>$mod['version'], 
                'mcversion'=>$mod['mcversion'],
                'md5'=>$mod['md5'], 
                'url'=>$mod['url'], 
                'filesize'=>$filesize
            ];

            array_push($modslist, $modentry);
        }
    }
    echo json_encode($modslist, JSON_UNESCAPED_SLASHES);
}
else if (preg_match("/api\/mod\/([\w\-\.]+)$/", $url, $matches)) { 
    $modslist=[];
    $modq = $db->query("SELECT * FROM mods WHERE name='{$matches[1]}'");
    if ($modq) {
        foreach ($modq as $mod) {
            $filesize = isset($mod['filesize']) ? $mod['filesize'] : 0;
            $modentry = [
                'pretty_name'=>$mod['pretty_name'],
                'name'=>$mod['name'], 
                'version'=>$mod['version'], 
                'mcversion'=>$mod['mcversion'],
                'md5'=>$mod['md5'], 
                'url'=>$mod['url'], 
                'filesize'=>$filesize
            ];
            array_push($modslist,$modentry);
        }
    }
    echo json_encode($modslist, JSON_UNESCAPED_SLASHES);
    
}
else if (preg_match("/api\/modpack$/", $url)) { // modpacks
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
                if ($build['public']==1 || $clientsq || $valid_client_key) {
                    $builds[$counter]=$build['name'];
                    $counter++;
>>>>>>> master
                }
            }
            $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$modpack['clients'].")");
            if ($modpack['public']==1 || $clientsq || $valid_client_key) {
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
            if ($modpack['public']==1 || $clientsq || $valid_client_key) {
                $mn = $modpack['name'];
                $mpn = $modpack['display_name'];
                $modpacks[$mn] = $mpn;
            }
        }
    }
    die(json_encode(["modpacks"=>$modpacks, "mirror_url"=>"http://".$config['host']."/mods"], JSON_UNESCAPED_SLASHES));
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
<<<<<<< HEAD
    print '{"error":"Modpack does not exist"}';
    exit();
}
$result = mysqli_query($conn, "SELECT * FROM `modpacks`");
while ($modpack=mysqli_fetch_array($result)) {
    if (uri($url, "api/modpack/".$modpack['name']."/".substr($url, strrpos($url, '/') + 1))) {
        $buildsres = mysqli_query($conn, "SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']);
        while ($build=mysqli_fetch_array($buildsres)) {
            if (uri($url, "api/modpack/".$modpack['name']."/".$build['name'])) {
                $clients = [];
                $clientsq = mysqli_query($conn, "SELECT * FROM `clients` WHERE `id` IN (".$build['clients'].")");
                while ($client=mysqli_fetch_array($clientsq)) {
                    array_push($clients, $client['UUID']);
                }
                if ($build['public']==1||in_array($_GET['cid'], $clients)||$_GET['k']==$config['api_key']) {
                    $mods = [];
                    $modslist= explode(',', $build['mods']);
                    $modnumber = 0;
                    foreach ($modslist as $mod) {
                        if ($mod !== "") {
                            $modsres = mysqli_query($conn, "SELECT * FROM `mods` WHERE `id` = ".$mod);
                            $modinfo=mysqli_fetch_array($modsres);
                            if ($modinfo['name']!== null && $modinfo['type'] !== null) {
                                if (isset($_GET['include'])) {
                                    if ($_GET['include']=="mods") {
                                        if (!$modinfo['url']) {
                                            $mods[$modnumber] = array(
                                                "name" => $modinfo['name'],
                                                "version" => $modinfo['version'],
                                                "md5" => $modinfo['md5'],
                                                "url" => "http://".$config['host'].$config['dir']
                                                    .$modinfo['type']."s/".$modinfo['filename'],
                                                "pretty_name" => $modinfo['pretty_name'],
                                                "author" => $modinfo['author'],
                                                "description" => $modinfo['description'],
                                                "link" => $modinfo['link'],
                                                "donate" => $modinfo['donlink']
                                            );
                                        } else {
                                            $mods[$modnumber] = array(
                                                "name" => $modinfo['name'],
                                                "version" => $modinfo['version'],
                                                "md5" => $modinfo['md5'],
                                                "url" => $modinfo['url'],
                                                "pretty_name" => $modinfo['pretty_name'],
                                                "author" => $modinfo['author'],
                                                "description" => $modinfo['description'],
                                                "link" => $modinfo['link'],
                                                "donate" => $modinfo['donlink']
                                            );
                                        }
                                    } else {
                                        if (!$modinfo['url']) {
                                            $mods[$modnumber] = array(
                                                "name" => $modinfo['name'],
                                                "version" => $modinfo['version'],
                                                "md5" => $modinfo['md5'],
                                                "url" => "http://".$config['host'].$config['dir'].$modinfo['type']
                                                    ."s/".$modinfo['filename']
                                            );
                                        } else {
                                            $mods[$modnumber] = array(
                                                "name" => $modinfo['name'],
                                                "version" => $modinfo['version'],
                                                "md5" => $modinfo['md5'],
                                                "url" => $modinfo['url']
                                            );
                                        }
                                    }
                                } else {
                                    if (!$modinfo['url']) {
                                        $mods[$modnumber] = array(
                                            "name" => $modinfo['name'],
                                            "version" => $modinfo['version'],
                                            "md5" => $modinfo['md5'],
                                            "url" => "http://".$config['host'].$config['dir'].$modinfo['type']."s/"
                                                .$modinfo['filename']
                                        );
                                    } else {
                                        $mods[$modnumber] = array(
                                            "name" => $modinfo['name'],
                                            "version" => $modinfo['version'],
                                            "md5" => $modinfo['md5'],
                                            "url" => $modinfo['url']
                                        );
                                    }
                                }
                                $modnumber++;
                            }
                        }
                    }
                    $response = array(
                        "minecraft" => str_replace("f", "", $build['minecraft']),
                        "java" => $build['java'],
                        "memory" => $build['memory'],
                        "forge" => null,
                        "mods" => $mods,
                    );
                    print(json_encode($response));
                    exit();
                } else {
                    print '{"error":"\n\r This build is private. Use Solder.cf to create private builds and modpacks :)
                     www.solder.cf\n\r Please contact '.$config['author'].' for more information."}';
                    exit();
=======

    foreach($modpackq as $modpack) { // sql col `name` isn't guaranteed to be unique...
        $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$modpack['clients'].")");
        if ($modpack['public']==1 || $clientsq || $valid_client_key) {
            $builds = [];
            $counter=0;
            $buildsq = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']);

            foreach($buildsq as $build) {
                $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$build['clients'].")");
                if ($build['public']==1 || $clientsq || $valid_client_key) {
                    $builds[$counter]=$build['name'];
                    $counter++;
>>>>>>> master
                }
            }

            if (isset($_GET['include']) && $_GET['include'] == "full") {
                $data=[
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
            } else {
                $data=[
                    "name" => $modpack['name'],
                    "display_name" => $modpack['display_name'],
                    "recommended" => $modpack['recommended_name'],
                    "latest" => $modpack['latest_name'],
                    "builds" => $builds
                ];
            }
            die(json_encode($data, JSON_UNESCAPED_SLASHES));
        } else {
            die('{"error":"This modpack is private."}');
        }
    }
// } else { // build details (including mods)
} elseif (preg_match("/api\/modpack\/([a-z\-|0-9]+)\/?([a-z\-|0-9\.]+)?$/", $url, $matches)) { // modpack details
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
            if ($build['public']==1 || $clientsq || $valid_client_key) {
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
                            "url" => !empty($mod['url']) ? $mod['url'] : $PROTO_STR.$config['host'].$config['dir'].$mod['type']."s/".$mod['filename'],
                            "filesize"=>0
                        ];
                    }
                    $modnumber++;
                }
                die(json_encode([
                    "minecraft" => str_replace("f", "", $build['minecraft']),
                    "forge" => null, // todo: is this a bool? or a forge version? or are there more keys for fabric/etc?
                    "java" => $build['java'],
                    "memory" => intval($build['memory']),
                    "mods" => $mods,
                ], JSON_UNESCAPED_SLASHES));
            } else {
                die('{"error":"\n\rThis build is private."}');
            }
        }
        die('{"error":"Build does not exist"}');
    }
}