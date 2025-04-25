<?php
header('Content-Type: application/json');
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('sanitize.php');

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->mods_delete() && !$perms->modloaders_delete()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (empty($_GET['id']) && empty($_GET['name'])) {
    die('{"status":error","message":"Id/name not specified."}');
}
if (!empty($_GET['id']) && !is_numeric($_GET['id'])) {
    die('{"status":error","message":"Malformed id"}');
}
if (!empty($_GET['name']) && !preg_match('/[\w\-_]+/', $_GET['name'])) {
    die('{"status":error","message":"Malformed name"}');
}

require_once("db.php");
$db = new Db;
$db->connect();

function removeMod($id) {
    global $db;

    if (isset($_GET['force']) && $_GET['force']=='true') {
        error_log('force deleting mod id='.$id);
    } else {
        $querybuilds = $db->query("SELECT id,name,mods FROM builds");
        foreach ($querybuilds as $build) {
            if (empty($querybuilds[0]['mods'])) {
                continue;
            }
            $build_mods = explode(',', $querybuilds[0]['mods']);
            $build_id = !empty($querybuilds[0]['id']) ? $querybuilds[0]['id'] : null;
            $build_name = !empty($querybuilds[0]['name']) ? $querybuilds[0]['name'] : null;
            if (in_array($id, $build_mods)) {
                return ["status"=>"error","message"=>"Cannot delete as it is in use!", "bid"=>$build_id, "bname"=>$build_name];
            }
        }
    }

    // get filename for mod id (and if it exists)
    $modq = $db->query("SELECT type,filename FROM `mods` WHERE `id` = '{$id}'");
    if ($modq) {
        if(sizeof($modq)==0) {
            return ["status"=>"error","message"=>"Specified id does not exist."];
        }
        $mod = $modq[0];
    }

    // remove it from db
    $db->execute("DELETE FROM `mods` WHERE `id` = '{$id}'");

    if ($mod['type']=='mod' && !(isset($_GET['force']) && $_GET['force']=='true')) {
        // check if theres any other mod entries with the same file
        $mod2q = $db->query("SELECT 1 FROM `mods` WHERE `filename` = '{$mod['filename']}' LIMIT 1");
        if ($mod2q && sizeof($mod2q)==1) {
            error_log('delete-mod.php: mod file is still in use, not removing');
            return ["status"=>"succ","message"=>"Mod version deleted from database, but file is still in use by other mod version."]; // leave file on disk
        }
    }
    if (file_exists("../".$mod['type']."s/".$mod['filename'])) {
        error_log('delete-mod.php: removing mod file');
        unlink("../".$mod['type']."s/".$mod['filename']);
    } else {
        error_log("delete-mod.php: couldn't find file!");
    }
    return ["status"=>"succ","message"=>"Mod version deleted."];
}

if(!empty($_GET['id'])) {
    $status = removeMod($_GET['id']);
    $json = @json_encode($status);
    if ($json === false) {
        error_log('delete-mod.php: could not encode status to json');
    }
    die($json); 
}
elseif (!empty($_GET['name'])) {
    // for all mod versions (ids) associated with name
    $modq = $db->query("SELECT * FROM `mods` WHERE `name` = '{$_GET['name']}'");

    $remove_failed=[];
    foreach ($modq as $mod) {
        error_log("removing name='{$_GET['name']}', id={$mod['id']}");
        $status = removeMod($mod['id']);
        if ($status['status']!='succ') {
            error_log('failed to remove, result='.json_encode($status));
            array_push($remove_failed, "{$mod['name']}-{$mod['version']}");
        }
    }
    if ($remove_failed) {
        die('{"status":"error","message":"The following mods could not be deleted; '.implode(',', $remove_failed).'"}');
    } else {
        die('{"status":"succ","message":"Mod \''.$_GET['name'].'\' deleted successfully."}');
    }
}
