<?php
header('Content-Type: application/json');

require_once('../functions/configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

require_once("../functions/db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$url = $_SERVER['REQUEST_URI'];
// remove query from $url
if (($querypos = strpos($url, "?")) !== FALSE) {
    $url = rtrim(substr($url, 0, $querypos), '/');
}

if ($config->exists('protocol')) {
    $protocol = strtolower($config->get('protocol'));
}
if (empty($protocol) || !in_array($protocol, ['http', 'https'])) {
    $protocol = strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL'])));
}
$protocol .= '://';

$dir = $config->get('dir');

$dev_builds = $config->exists('dev_builds') && $config->get('dev_builds') === 'on';

$server_wide_api_key='';
if (!empty($config->get('api_key'))) {
    $server_wide_api_key = $config->get('api_key');
}

$client_uuid = isset($_GET['cid']) ? $_GET['cid'] : '';

// this key should always have access.
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

// returns TRUE on exact match, FALSE on no match, or string with arg/param.
function endpoint_arg($url, $endpoint): string|bool {
    global $dir;
    // matches endpoint exactly
    if ($url === $dir.$endpoint) {
        return TRUE;
    }
    $pos = strpos($url, $dir.$endpoint);
    // doesn't match endpoint
    if ($pos === FALSE) {
        return FALSE;
    } 
    // matches endpoint
    else {
        $arg = substr($url, $pos + strlen($dir.$endpoint));
        return $arg;
    }
}

// api => api/, as it's a directory
if (($arg = endpoint_arg($url, 'api/')) === TRUE) {
    die('{"api":"Solder.cf","version":"v1.4.0","stream":"'.($dev_builds ? 'Dev' : 'Release').'"}');
}
elseif (($arg = endpoint_arg($url, 'api/verify')) === TRUE) {
    die('{"error":"No API key provided."}');
}
elseif (($arg = endpoint_arg($url, 'api/verify/')) !== FALSE) {
    $client_api_key = $arg;
    if ($server_wide_api_key) {
        if ($arg === $server_wide_api_key) {
            die('{"valid":"Key validated.","name":"API KEY","created_at":"A long time ago"}');
        }
    } else {
        // query db. multiple users could use the same technic api key...
        $apikeysq = $db->query("SELECT 1 FROM users WHERE api_key='{$client_api_key}'");
        if ($apikeysq) {
            die('{"valid":"Key validated.","name":"API KEY","created_at":"A long time ago"}');
        }
    }
    die('{"error":"Invalid key provided."}');
}
elseif (($arg = endpoint_arg($url, 'api/loader')) !== FALSE) {
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
    die(json_encode($modslist, JSON_UNESCAPED_SLASHES));
}
elseif (($arg = endpoint_arg($url, 'api/mod')) === TRUE) {
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
                'pretty_name'=>htmlspecialchars($mod['pretty_name']),
                'name'=>$mod['name'], 
                'version'=>$mod['version'], 
                'mcversion'=>$mod['mcversion'],
                'md5'=>$mod['md5'], 
                'url'=> !empty($mod['url']) ? $mod['url'] : $protocol.$config->get('host').$config->get('dir').$mod['type']."s/".$mod['filename'],
                'filesize'=>$filesize,
                'author'=>htmlspecialchars($mod['author']),
                'loader'=>$mod['loadertype']
            ];

            array_push($modslist, $modentry);
        }
    }
    die(json_encode($modslist, JSON_UNESCAPED_SLASHES));
}
elseif (($arg = endpoint_arg($url, 'api/mod/')) !== FALSE) {
    $modname = $arg;
    $modslist=[];
    $modq = $db->query("SELECT * FROM mods WHERE name='{$modname}'");
    if (!$modq) {
        die('{"error":"Mod does not exist."}');
    }
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
    die(json_encode($modslist, JSON_UNESCAPED_SLASHES));
}
elseif (($arg = endpoint_arg($url, 'api/modpack')) === TRUE) {
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
            $buildsq = $db->query("SELECT * FROM `builds` WHERE `modpack` = {$modpack['id']} AND minecraft IS NOT NULL");

            foreach($buildsq as $build) {
                if ($build['minecraft'] === null) {
                    continue;
                }
                $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$build['clients'].")");
                if ($build['public']==1 || $clientsq || $valid_client_key) {
                    array_push($builds, $build['name']);
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
    die(json_encode(["modpacks"=>$modpacks, "mirror_url"=>$protocol.$config->get('host')."/mods"], JSON_UNESCAPED_SLASHES));
} 
elseif (($arg = endpoint_arg($url, 'api/modpack/')) !== FALSE) {
    $uri_modpack = $arg;

    // if a build is specified
    $position = strpos($arg, '/');
    if ($position !== FALSE) {
        $uri_modpack = substr($arg, 0, $position);
        $uri_build = substr($arg, $position + 1);

        // could use a join on/using for better speed.
        $modpacksq = $db->query("SELECT * FROM `modpacks` WHERE name='".$db->sanitize($uri_modpack)."'");
        if (!$modpacksq) {
            die('{"error":"Modpack does not exist."}');
        }
        // name isn't unique in db/modpacks
        if (sizeof($modpacksq)>1) {
            error_log("{$uri}: got multiple modpack results for name. Exiting.");
            die('{"error":"Got multiple modpack results for name."}');
        }
        $modpack = $modpacksq[0];

        $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '{$db->sanitize($client_uuid)}' AND `id` IN ({$modpack['clients']})");
        if ($modpack['public']!=1 && !$clientsq && !$valid_client_key) {
            die('{"error":"This modpack is private."}');
        }

        $buildsq = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']." AND name='".$db->sanitize($uri_build)."'");
        if (!$buildsq) {
            die('{"error":"Build does not exist."}');
        }
        // modpack name isn't unique in db/builds
        if (sizeof($buildsq)>1) {
            error_log("{$uri}: got multiple build results for name. Exiting.");
            die('{"error":"Got multiple build results for name."}');
        }
        $build = $buildsq[0];

        if ($build['minecraft'] === null) {
            die('{"error":"Build does not exist"}');
        }

        $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '{$db->sanitize($client_uuid)}' AND `id` IN ({$build['clients']})");
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

                $filesize = isset($mod['filesize']) ? $mod['filesize'] : 0;
                if (isset($_GET['include']) && $_GET['include']=="mods") {
                    $mods[$modnumber] = [
                        "name" => $mod['name'],
                        "version" => $mod['version'],
                        "md5" => $mod['md5'],
                        "url" => !empty($mod['url']) ? $mod['url'] : $protocol.$config->get('host').$config->get('dir').$mod['type']."s/".$mod['filename'],
                        "pretty_name" => $mod['pretty_name'],
                        "author" => $mod['author'],
                        "description" => $mod['description'],
                        "link" => $mod['link'],
                        "donate" => $mod['donlink'],
                        "filesize" => $filesize
                    ];
                } else {
                    $mods[$modnumber] = [
                        "name" => $mod['name'],
                        "version" => $mod['version'],
                        "md5" => $mod['md5'],
                        "url" => !empty($mod['url']) ? $mod['url'] : $protocol.$config->get('host').$config->get('dir').$mod['type']."s/".$mod['filename'],
                        "filesize" => $filesize
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
            die('{"error":"This build is private."}');
        }
        die('{"error":"Build does not exist"}');
    }
    // no build specified, show all builds
    else {
        $modpacksq = $db->query("
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
        if (!$modpacksq) {
            die('{"error":"Modpack does not exist"}');
        }
        // name isn't unique in db/modpacks
        if (sizeof($modpacksq)>1) {
            error_log("{$uri}: got multiple results for modpack name. Exiting.");
            die('{"error":"Got multiple results for modpack name"}');
        }
        $modpack = $modpacksq[0];

        $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$modpack['clients'].")");
        if ($modpack['public']==1 || $clientsq || $valid_client_key) {
            // set details of modpack
            if (isset($_GET['include']) && $_GET['include'] == "full") {
                $modpack_info=[
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
                    "builds" => [] //set later
                ];
            } else {
                $modpack_info=[
                    "name" => $modpack['name'],
                    "display_name" => $modpack['display_name'],
                    "recommended" => $modpack['recommended_name'],
                    "latest" => $modpack['latest_name'],
                    "builds" => [] // set later
                ];
            }

            // set build names of modpack
            $buildsq = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']);
            foreach ($buildsq as $build) {
                if ($build['minecraft'] === null) {
                    continue;
                }
                $clientsq = $db->query("SELECT 1 FROM `clients` WHERE `UUID` = '".$db->sanitize($client_uuid)."' AND `id` IN (".$build['clients'].")");
                if ($build['public']==1 || $clientsq || $valid_client_key) {
                    array_push($modpack_info['builds'], $build['name']);
                }
            }

            die(json_encode($modpack_info, JSON_UNESCAPED_SLASHES));
        } else {
            die('{"error":"This modpack is private."}');
        }
    }
}

die('{"error":"Invalid API usage."}');