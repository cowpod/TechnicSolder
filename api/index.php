<?php
error_reporting(0);
header('Content-Type: application/json');
$url = $_SERVER['REQUEST_URI'];
if (strpos($url, '?') !== false) {
    $url = substr($url, 0, strpos($url, "?"));
}
if (substr($url, -1)=="/" & substr($url, -4)!=="api/") {
    if ($_SERVER['QUERY_STRING']!=="") {
        header("Location: " . rtrim($url, '/') . "?" . $_SERVER['QUERY_STRING']);
    } else {
        header("Location: " . rtrim($url, '/'));
    }
}
$config = require("../functions/config.php");

function uri($url, $uri)
{
    $length = strlen($uri);
    if ($length == 0) {
        return true;
    }
    return (substr($url, -$length) === $uri);
}

if(uri($url,"api/")){
	print '{"api":"Solder.cf","version":"v1.3.4","stream":"Release"}';
	exit();
} 
if (uri($url, "api/verify")) {
    print '{"error":"No API key provided."}';
    exit();
}
if (uri($url,"api/verify/".substr($url, strrpos($url, '/') + 1))) {
    if (substr($url, strrpos($url, '/') + 1)==$config['api_key']) {
        print '{"valid":"Key validated.","name":"API KEY","created_at":"A long time ago"}';
        exit();
    }
    print '{"error":"Invalid key provided."}';
    exit();
}

require("../functions/db.php");
$db=new Db;
$db->connect();

if (uri($url,"api/modpack")) {
    if (isset($_GET['include'])) {
        if ($_GET['include'] == "full") {
            $result = $db->query("SELECT * FROM `modpacks`");
            $modpacks = array();
            foreach($result as $modpack) {
                $buildsres = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']);
                $builds = [];
                foreach($buildsres as $build) {
                    $clients = [];
                    $clientsq = $db->query("SELECT * FROM `clients` WHERE `id` IN (".$build['clients'].")");
                    foreach($clientsq as $client) {
                        array_push($clients, $client['UUID']);
                    }
                    if ($build['public']==1||in_array($_GET['cid'],$clients)||$_GET['k']==$config['api_key']) {
                        array_push($builds, $build['name']);
                    }
                }
                $clients = [];
                $clientsq = $db->query("SELECT * FROM `clients` WHERE `id` IN (".$modpack['clients'].")");
                foreach($clientsq as $client) {
                    array_push($clients, $client['UUID']);
                }
                if ($modpack['public']==1||in_array($_GET['cid'],$clients)||$_GET['k']==$config['api_key']) {
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
            $result = $db->query("SELECT * FROM `modpacks`");
            foreach($result as $modpack) {
                $clients = [];
                $clientsq = $db->query("SELECT * FROM `clients` WHERE `id` IN (".$modpack['clients'].")");
                foreach($clientsq as $client) {
                    array_push($clients, $client['UUID']);
                }
                if ($modpack['public']==1||in_array($_GET['cid'],$clients)||$_GET['k']==$config['api_key']) {
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
        $result = $db->query("SELECT * FROM `modpacks`");
        foreach($result as $modpack) {
            $clients = [];
            $clientsq = $db->query("SELECT * FROM `clients` WHERE `id` IN (".$modpack['clients'].")");
            foreach($clientsq as $client) {
                array_push($clients, $client['UUID']);
            }
            if ($modpack['public']==1||in_array($_GET['cid'],$clients)||$_GET['k']==$config['api_key']) {
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
    print(json_encode($response));
    exit();
}
if (uri($url,"api/modpack/".substr($url, strrpos($url, '/') + 1))) {
    $result = $db->query("SELECT * FROM `modpacks`");
    foreach($result as $modpack) {
        if (uri($url,"api/modpack/".$modpack['name'])) {
            $clients = [];
            $clientsq = $db->query("SELECT * FROM `clients` WHERE `id` IN (".$modpack['clients'].")");
            foreach($clientsq as $client) {
                array_push($clients, $client['UUID']);
            }
            if ($modpack['public']==1||in_array($_GET['cid'],$clients)||$_GET['k']==$config['api_key']) {
                $buildsres = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']);
                $builds = [];
                foreach($buildsres as $build) {
                    $clients = [];
                    $clientsq = $db->query("SELECT * FROM `clients` WHERE `id` IN (".$build['clients'].")");
                    foreach($clientsq as $client) {
                        array_push($clients, $client['UUID']);
                    }
                    if ($build['public']==1||in_array($_GET['cid'],$clients)||$_GET['k']==$config['api_key']) {
                        array_push($builds, $build['name']);
                    }
                }
                $response = array(
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
                print(json_encode($response));
                exit();
            } else {
                print '{"error":"This modpack is private."}';
                exit();
            }
        }

    }
    print '{"error":"Modpack does not exist"}';
    exit();
}
$result = $db->query("SELECT * FROM `modpacks`");
foreach($result as $modpack) {
    if (uri($url,"api/modpack/".$modpack['name']."/".substr($url, strrpos($url, '/') + 1))) {
        $buildsres = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']);
        foreach($buildsres as $build) {
            if (uri($url,"api/modpack/".$modpack['name']."/".$build['name'])) {
                $clients = [];
                $clientsq = $db->query("SELECT * FROM `clients` WHERE `id` IN (".$build['clients'].")");
                foreach($clientsq as $client) {
                    array_push($clients, $client['UUID']);
                }
                if ($build['public']==1||in_array($_GET['cid'],$clients)||$_GET['k']==$config['api_key']) {
                    $mods = [];
                    $modslist= explode(',', $build['mods']);
                    $modnumber = 0;
                    foreach($modslist as $mod) {
                        if ($mod !== "") {
                            $modsres = $db->query("SELECT * FROM `mods` WHERE `id` = ".$mod);
                            $modinfo=($modsres);
                            if ($modinfo['name']!== null && $modinfo['type'] !== null) {
                                if (isset($_GET['include'])) {
                                    if ($_GET['include']=="mods") {
                                        if (!$modinfo['url']) {
                                            $mods[$modnumber] = array(
                                                "name" => $modinfo['name'],
                                                "version" => $modinfo['version'],
                                                "md5" => $modinfo['md5'],
                                                "url" => "http://".$config['host'].$config['dir'].$modinfo['type']."s/".$modinfo['filename'],
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
                                                "url" => "http://".$config['host'].$config['dir'].$modinfo['type']."s/".$modinfo['filename']
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
                                            "url" => "http://".$config['host'].$config['dir'].$modinfo['type']."s/".$modinfo['filename']
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
                }
            }
        }
        print '{"error":"Build does not exist"}';
        exit();
    }
}
print '{"status":404,"error":"Not Found"}';
