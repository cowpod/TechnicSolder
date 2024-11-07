<?php
header('Content-Type: application/json');
session_start();

if (empty($_GET['id']) && empty($_GET['name'])) {
    die("Id/name not specified.");
}
// if (empty($_GET['force'])) { // assume false
//     die("Force not specified.");
// }
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (substr($_SESSION['perms'], 4, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}

require_once("db.php");
$db=new Db;
$db->connect();

if (!empty($_GET['name'])) {
    // delete all ids associated with name, if not in a build
    $modq = $db->query("
        SELECT id,type,filename
        FROM `mods` 
        WHERE `name` = '".$db->sanitize($_GET['name'])."'"
    );
    foreach ($modq as $mod) {
        if (!empty($_GET['force'])&&$_GET['force']=='true') {
            error_log('force deleting mod name='.$_GET['name'].', id='.$mod['id']);
        } else {
            // check if a build has this id
            $buildq = $db->query("
                SELECT id,name 
                FROM builds 
                WHERE ',' || mods || ',' LIKE '%,' || ".$mod['id']." || ',%';
            ");
            if ($buildq && sizeof($buildq)>0) {
                // mod is in a build
                // only get first build
                die('{"status":"warn","message":"Cannot delete mod as it is in use!","bid":"'.$buildq[0]['id'].'","bname":"'.$buildq[0]['name'].'"}');
            }
        } 

        // mod not in any build
        $db->execute("DELETE FROM `mods` WHERE `id` = '".$mod['id']."'");

        // check if theres any other mod entries with the same file
        if ($mod['type']=='mod' && !(isset($_GET['force']) && $_GET['force']=='true')) {
            $mod2q = $db->query("SELECT COUNT(*) as count FROM `mods` WHERE `filename` = '".$mod['filename']."'");
            if ($mod2q && sizeof($mod2q)==1 && $mod2q[0]['count']>0) {
                continue; // leave file on disk
            }
        } else {
            unlink("../".$mod['type']."s/".$mod['filename']);
        }
    }
    
    die('{"status":"succ","message":"All versions of mod deleted."}');

} elseif(!empty($_GET['id'])) {
    if (isset($_GET['force']) && $_GET['force']=='true') {
        error_log('force deleting mod id='.$_GET['id']);
    } else {
        $querybuilds = $db->query("SELECT id,name,mods FROM builds");
        foreach ($querybuilds as $build) {
            if (empty($querybuilds[0]['mods'])) {
                continue;
            }
            $build_mods = explode(',', $querybuilds[0]['mods']);
            $build_id = !empty($querybuilds[0]['id']) ? $querybuilds[0]['id'] : null;
            $build_name = !empty($querybuilds[0]['name']) ? $querybuilds[0]['name'] : null;
            if (in_array($_GET['id'], $build_mods)) {
                die('{"status":"error","message":"Cannot delete as it is in use!", "bid":"'.$build_id.'", "bname":"'.$build_name.'"}');
            }
        }
    }

    // get filename for mod id (and if it exists)
    $modq = $db->query("SELECT type,filename FROM `mods` WHERE `id` = '".$db->sanitize($_GET['id'])."'");
    if ($modq) {
        if(sizeof($modq)==0) {
            die('{"status":"error","message":"Specified id does not exist."}');
        }
        $mod = $modq[0];
    }

    // remove it from db
    $db->execute("DELETE FROM `mods` WHERE `id` = '".$db->sanitize($_GET['id'])."'");

    if ($mod['type']=='mod' && (!isset($_GET['force']) || !$_GET['force']=='true')) {
        // check if theres any other mod entries with the same file
        $mod2q = $db->query("SELECT COUNT(*) AS count FROM `mods` WHERE `filename` = '".$mod['filename']."'");
        if ($mod2q && sizeof($mod2q)==1 && $mod2q[0]['count']>0) {
            die('{"status":"succ","message":"Mod version deleted from database, but file is still in use by other mod version."}'); // leave file on disk
        }
    } else {
        if (file_exists("../".$mod['type']."s/".$mod['filename'])) {
            unlink("../".$mod['type']."s/".$mod['filename']);
        }
        die('{"status":"succ","message":"Mod version deleted."}');
    }
}
