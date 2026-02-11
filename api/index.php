<?php

header('Content-Type: application/json');

require_once('../functions/configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}

require_once("../functions/db.php");
if (!isset($db)) {
    $db = new Db();
    $db->connect();
}

$url = $_SERVER['REQUEST_URI'];
// remove query from $url
if (($querypos = strpos($url, "?")) !== false) {
    $url = rtrim(substr($url, 0, $querypos), '/');
}

if ($config->exists('protocol') && !empty($config->get('protocol'))) {
    $protocol = strtolower($config->get('protocol')).'://';
} else {
    $protocol = strtolower(current(explode('/', $_SERVER['SERVER_PROTOCOL']))).'://';
}
$url_prefix = $protocol.$config->get('host').$config->get('dir');

$dir = $config->get('dir');

$dev_builds = $config->exists('dev_builds') && $config->get('dev_builds') === 'on';

$api_key = '';
if (!empty($config->get('api_key'))) {
    $api_key = $config->get('api_key');
}

$client_uuid = isset($_GET['cid']) ? $_GET['cid'] : '';

// this key should always have access.
$valid_client_key = false;
if (isset($_GET['k'])) {
    if ($api_key) {
        if ($_GET['k'] == $api_key) {
            $valid_client_key = true;
        }
    }
}

// returns TRUE on exact match, FALSE on no match, or string with arg/param.
function endpoint_arg($url, $endpoint): string|bool
{
    global $dir;
    // matches endpoint exactly
    if ($url === $dir.$endpoint) {
        return true;
    }
    $pos = strpos($url, $dir.$endpoint);
    // doesn't match endpoint
    if ($pos === false) {
        return false;
    }
    // matches endpoint
    else {
        $arg = substr($url, $pos + strlen($dir.$endpoint));
        return $arg;
    }
}

function get_mods($type, $loadertype, $mcversion): array {
    global $url_prefix;
    global $config;
    global $db;

    $url = $url_prefix.'mods/';
    return $db->query("
        SELECT
            id,
            name,
            pretty_name,
            version,
            mcversion,
            md5,
            IFNULL(NULLIF(url,''), CONCAT({$db->quote($url)}, filename)) AS url,
            filesize,
            author,
            loadertype loader
        FROM mods 
        WHERE type = '{$type}'" . (!empty($loadertype) ? "
        AND loadertype = '{$loadertype}'" : '') . (!empty($mcversion) ? "
        AND (
            {$db->quote($mcversion)} = mcversion
            OR (
                (SUBSTR(mcversion, 1, 1) = '[' AND {$db->quote($mcversion)} >= SUBSTR(mcversion, 2, INSTR(mcversion, ',') - 2))
                OR
                (SUBSTR(mcversion, 1, 1) = '(' AND {$db->quote($mcversion)} > SUBSTR(mcversion, 2, INSTR(mcversion, ',') - 2))
            )
            AND (
                (SUBSTR(mcversion, -1, 1) = ']' AND {$db->quote($mcversion)} <= SUBSTR(mcversion, INSTR(mcversion, ',') + 2, LENGTH(mcversion) - INSTR(mcversion, ',') - 2))
                OR
                (SUBSTR(mcversion, -1, 1) = ')' AND {$db->quote($mcversion)} < SUBSTR(mcversion, INSTR(mcversion, ',') + 2, LENGTH(mcversion) - INSTR(mcversion, ',') - 2))
            )
        )" : '')
    ) ?: [];
}

// api => api/, as it's a directory
if (($arg = endpoint_arg($url, 'api/')) === true) {
    die('{"api":"Solder.cf","version":"v2.0","stream":"'.($dev_builds ? 'Dev' : 'Release').'"}');
} 
elseif (($arg = endpoint_arg($url, 'api/verify')) === true
    || ($arg = endpoint_arg($url, 'api/verify/')) === true) {
    die('{"error":"No API key provided."}');
} 
elseif (($arg = endpoint_arg($url, 'api/verify/')) !== false) {
    $client_api_key = $arg;
    if ($api_key) {
        if ($arg === $api_key) {
            die('{"valid":"Key validated.","name":"API KEY","created_at":"A long time ago"}');
        }
    }
    die('{"error":"Invalid key provided."}');
} 
elseif (($arg = endpoint_arg($url, 'api/loader')) === true
    
    || ($arg = endpoint_arg($url, 'api/loader/')) === true) {
    if (!empty($_GET['loadertype']) && ctype_alnum($_GET['loadertype'])) {
        $loadertype = $_GET['loadertype'];
    } else {
        $loadertype = '';
    }
    if (!empty($_GET['mcversion']) && preg_match('/^[a-zA-Z0-9\-\.]+$/', $_GET['mcversion'])) {
        $mcversion = $_GET['mcversion'];
    } else {
        $mcversion = '';
    }

    $mods = get_mods('forge', $loadertype, $mcversion);

    die(@json_encode($mods, JSON_UNESCAPED_SLASHES) ?: '[]');
} 
elseif (($arg = endpoint_arg($url, 'api/mod')) === true
    || ($arg = endpoint_arg($url, 'api/mod/')) === true) {
    if (!empty($_GET['loadertype']) && ctype_alnum($_GET['loadertype'])) {
        $loadertype = $_GET['loadertype'];
    } else {
        $loadertype = '';
    }
    if (!empty($_GET['mcversion']) && preg_match('/^[a-zA-Z0-9\-\.]+$/', $_GET['mcversion'])) {
        $mcversion = $_GET['mcversion'];
    } else {
        $mcversion = '';
    }

    $mods = get_mods('mod', $loadertype, $mcversion);

    die(@json_encode($mods, JSON_UNESCAPED_SLASHES) ?: '[]');
} 
elseif (($arg = endpoint_arg($url, 'api/mod/')) !== false) {
    if (!preg_match('/^[\w\-]+$/',$arg)) {
        die("Malformed slug");
    }
    $url = $url_prefix.'mods/';
    $mods = $db->query("
        SELECT
            id,
            name,
            pretty_name,
            version,
            mcversion,
            md5,
            IFNULL(NULLIF(url,''), CONCAT({$db->quote($url)}, filename)) AS url,
            filesize
        FROM mods 
        WHERE type = 'mod'
        AND name = {$db->quote($arg)}
    ") ?: [];
    if (!$mods) {
        die('{"error":"Mod does not exist."}');
    }
    die(@json_encode($mods, JSON_UNESCAPED_SLASHES) ?: '[]');
} 
elseif (($arg = endpoint_arg($url, 'api/modpack')) === true
    || ($arg = endpoint_arg($url, 'api/modpack/')) === true) {
    $modpacks = [];

    if (isset($_GET['include']) && $_GET['include'] == "full") {
        $modpacksq = $db->query("
            WITH client_modpacks AS (
                SELECT m.id
                FROM modpack_clients mc
                JOIN modpacks m
                ON m.id = mc.modpack_id 
                JOIN clients c
                ON c.id = mc.client_id
                WHERE c.UUID = {$db->quote($client_uuid)}
            ),
            client_builds AS (
                SELECT b.id
                FROM build_clients bc
                JOIN builds b
                ON b.id = bc.build_id
                JOIN clients c
                ON c.id = bc.client_id
                WHERE c.UUID = {$db->quote($client_uuid)}
            )
            SELECT
                m.name AS name,
                m.display_name AS display_name,
                m.url AS url,
                m.icon AS icon,
                m.icon_md5 AS icon_md5,
                m.logo AS logo,
                m.logo_md5 AS logo_md5,
                m.background AS background,
                m.background_md5 AS background_md5,
                b_latest.name AS latest, 
                b_recommended.name AS recommended,
                GROUP_CONCAT(b_all.name) AS builds
            FROM modpacks AS m 
            LEFT JOIN builds AS b_latest 
                ON m.latest = b_latest.id 
                AND b_latest.minecraft IS NOT NULL
                AND (
                    b_latest.public = 1
                    OR b_latest.id IN (SELECT id FROM client_builds)
                )
            LEFT JOIN builds AS b_recommended 
                ON m.recommended = b_recommended.id
                AND b_recommended.minecraft IS NOT NULL
                AND (
                    b_recommended.public = 1
                    OR b_recommended.id IN (SELECT id FROM client_builds)
                )
            LEFT JOIN builds b_all
                ON b_all.modpack = m.id
                AND b_all.minecraft IS NOT NULL
                AND (
                    b_all.public = 1
                    OR b_all.id IN (SELECT id FROM client_builds)
                )
            LEFT JOIN client_modpacks cm
                ON m.id = cm.id
            WHERE m.public = 1
            OR cm.id IS NOT NULL
            GROUP BY m.id
        ") ?: [];

        foreach ($modpacksq as $modpack) {
            $modpacks[$modpack['name']] = $modpack;
            $modpacks[$modpack['name']]['builds'] = !empty($modpack['builds']) ? explode(',', $modpack['builds']) : [];
        }
    } else {
        $modpacksq = $db->query("
            WITH client_modpacks AS (
                SELECT m.id
                FROM modpack_clients mc
                JOIN modpacks m
                ON m.id = mc.modpack_id 
                JOIN clients c
                ON c.id = mc.client_id
                WHERE c.UUID = {$db->quote($client_uuid)}
            )
            SELECT 
                m.name,
                m.display_name
            FROM modpacks m
            LEFT JOIN client_modpacks cm
                ON m.id = cm.id
            WHERE m.public = 1
            OR cm.id IS NOT NULL
        ") ?: [];

        foreach ($modpacksq as $modpack) {
            $modpacks[$modpack['name']] = $modpack['display_name'];
        }
    }

    die(@json_encode(["modpacks"=>$modpacks, "mirror_url" => $url_prefix.'mods/'], JSON_UNESCAPED_SLASHES) ?: '[]');
} 
elseif (($arg = endpoint_arg($url, 'api/modpack/')) !== false) {
    $uri_modpack = $arg;

    $url = $url_prefix.'mods/';

    // if a build is specified
    // show build and it's mods
    // todo: show mod details as well! name, version, md5, url, filesize
    $position = strpos($arg, '/');
    if ($position !== false) {
        $uri_modpack = substr($arg, 0, $position);
        $uri_build = substr($arg, $position + 1);

        $builds = $db->query("
            WITH client_modpacks AS (
                SELECT m.id
                FROM modpack_clients mc
                JOIN modpacks m
                ON m.id = mc.modpack_id 
                JOIN clients c
                ON c.id = mc.client_id
                WHERE c.UUID = {$db->quote($client_uuid)}
            ),
            client_builds AS (
                SELECT b.id
                FROM build_clients bc
                JOIN builds b
                ON b.id = bc.build_id
                JOIN clients c
                ON c.id = bc.client_id
                WHERE c.UUID = {$db->quote($client_uuid)}
            )
            SELECT 
                b.minecraft,
                NULL AS forge,
                b.java,
                b.memory,".($config->get('db-type')==='sqlite' ? "
                json_group_array(
                    json_object(
                        'name', mods.name,
                        'version', mods.version,
                        'md5', mods.md5,
                        'url', IFNULL(NULLIF(mods.url,''), CONCAT({$db->quote($url)}, mods.filename)),
                        'filesize', mods.filesize
                    )
                ) AS mods" : "
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'name', mods.name,
                        'version', mods.version,
                        'md5', mods.md5,
                        'url', IFNULL(NULLIF(mods.url,''), CONCAT({$db->quote($url)}, mods.filename)),
                        'filesize', mods.filesize
                    )
                ) AS mods")."
            FROM modpacks m
            JOIN builds b
                ON b.modpack = m.id
                AND b.minecraft IS NOT NULL
                AND (
                    b.public = 1
                    OR b.id IN (SELECT id FROM client_builds)
                )
                AND b.name = {$db->quote($uri_build)}
            LEFT JOIN build_mods bm
                ON bm.build_id = b.id
            LEFT JOIN mods
                ON mods.id = bm.mod_id
            LEFT JOIN client_modpacks cm
                ON m.id = cm.id
            WHERE m.name = {$db->quote($uri_modpack)}
            AND (
                m.public = 1
                OR cm.id IS NOT NULL
            )
            GROUP BY b.id
        ") ?: [];
        if (!$builds) {
            die('{"error":"Build does not exist or is private."}');
        }

        foreach($builds as $build) {
            if (!empty($build['mods'])) {
                $decoded = @json_decode($build['mods']);
                if ($decoded !== null) {
                    $build['mods'] = $decoded;
                }
            }
            die(@json_encode($build, JSON_UNESCAPED_SLASHES) ?: '[]');
            break;
        }
    }
    // no build specified, show all builds
    else {
        if (!preg_match('/^[\w\-]+$/',$uri_modpack)) {
            die("Malformed modpack");
        }

        $full = isset($_GET['include']) && $_GET['include'] == "full";

        $modpacks = $db->query("
            WITH client_modpacks AS (
                SELECT m.id
                FROM modpack_clients mc
                JOIN modpacks m
                ON m.id = mc.modpack_id 
                JOIN clients c
                ON c.id = mc.client_id
                WHERE c.UUID = {$db->quote($client_uuid)}
            ),
            client_builds AS (
                SELECT b.id
                FROM build_clients bc
                JOIN builds b
                ON b.id = bc.build_id
                JOIN clients c
                ON c.id = bc.client_id
                WHERE c.UUID = {$db->quote($client_uuid)}
            )
            SELECT
                m.name AS name,
                m.display_name AS display_name, ".($full ? "
                IFNULL(NULLIF(m.url,''), CONCAT({$db->quote($url)}, m.filename)),
                m.icon AS icon,
                m.icon_md5 AS icon_md5,
                m.logo AS logo,
                m.logo_md5 AS logo_md5,
                m.background AS background,
                m.background_md5 AS background_md5, " : '')."
                b_latest.name AS latest, 
                b_recommended.name AS recommended,
                GROUP_CONCAT(b_all.name) AS builds -- we explode it later
            FROM modpacks AS m 
            LEFT JOIN builds AS b_latest 
                ON m.latest = b_latest.id 
                AND b_latest.minecraft IS NOT NULL
                AND (
                    b_latest.public = 1
                    OR b_latest.id IN (SELECT id FROM client_builds)
                )
            LEFT JOIN builds AS b_recommended 
                ON m.recommended = b_recommended.id
                AND b_recommended.minecraft IS NOT NULL
                AND (
                    b_recommended.public = 1
                    OR b_recommended.id IN (SELECT id FROM client_builds)
                )
            LEFT JOIN builds b_all
                ON b_all.modpack = m.id
                AND b_all.minecraft IS NOT NULL
                AND (
                    b_all.public = 1
                    OR b_all.id IN (SELECT id FROM client_builds)
                )
            LEFT JOIN client_modpacks cm
                ON m.id = cm.id
            WHERE m.name = {$db->quote($uri_modpack)}
            AND (
                m.public = 1
                OR cm.id IS NOT NULL
            )
            GROUP BY m.id
        ") ?: [];
        if (!$modpacks) {
            die('{"error":"Modpack does not exist or is private."}');
        }

        foreach ($modpacks as $modpack) {
            if (!empty($modpack['builds'])) {
                $modpack['builds'] = explode(',',$modpack['builds']);
            }

            die(@json_encode($modpack, JSON_UNESCAPED_SLASHES) ?: '[]');

            break;
        }
    }
}

die('{"error":"Invalid API usage."}');
