<?php

header('Content-Type: application/json');
session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->mods_delete() && !$perms->modloaders_delete()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

if (empty($_GET['id']) && empty($_GET['name'])) {
    die('{"status":"error","message":"Id/name not specified."}');
}
if (!empty($_GET['id']) && !is_numeric($_GET['id'])) {
    die('{"status":error","message":"Malformed id"}');
}
if (!empty($_GET['name']) && !preg_match('/[\w\-_]+/', $_GET['name'])) {
    die('{"status":error","message":"Malformed name"}');
}

require_once("db.php");
$db = new Db();
$db->connect();

function removeMod($id) {
    global $db;

    // get filename for mod id (and if it exists)
    $modq = $db->query("SELECT type,filename FROM mods WHERE id = {$id}");
    if (!$modq) {
        return ["status" => "error","message" => "Specified id does not exist."];
    }
    $mod = $modq[0];

    // check if its in use by a build.
    // todo: not just the first build!
    $modinuseq = $db->query("
        SELECT 1
        FROM build_mods
        WHERE mod_id = {$id}
        LIMIT 1
    ");
    if ($modinuseq) {
        if (isset($_GET['force']) && $_GET['force'] == 'true') {
            if (!$db->execute("DELETE FROM build_mods WHERE mod_id = {$id}")) {
                return ["status" => "error","message" => "Cannot delete from build!"];
            }
        } else {
            return ["status" => "error","message" => "Cannot delete as it is in use!"];
        }
    }
    
    // remove mod from db
    if (!$db->execute("DELETE FROM mods WHERE id = {$id}")) {
        return ["status" => "error","message" => "Could not delete mod"];
    }

    // check if theres any other mod entries with the same file
    $fileinuseq = $db->query("
        SELECT 1 
        FROM mods 
        WHERE type = 'mod' 
        AND filename = {$db->quote($mod['filename'])}
        LIMIT 1
    ");
    if ($fileinuseq) {
        return ["status" => "succ","message" => "Mod version deleted from database, but file is still in use by other mod version."]; // leave file on disk   
    }

    if (file_exists("../".$mod['type']."s/".$mod['filename'])) {
        unlink("../".$mod['type']."s/".$mod['filename']);
    }

    return ["status" => "succ","message" => "Mod version deleted."];
}

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

// delete by id
if (!empty($_GET['id'])) {
    $status = removeMod($_GET['id']);
    $json = @json_encode($status);
    if ($json === false) {
        die('{"status":"error","message":"Could not encode status"}');
    }

    if (!$db->commit()) {
        die('{"status":"error","message":"Could not commit changes"}');
    }
    die($json);
}

// delete by name
elseif (!empty($_GET['name'])) {
    // for all mod versions (ids) associated with name
    $modq = $db->query("SELECT * FROM mods WHERE name = {$db->quote($_GET['name'])}");

    $remove_failed = [];
    foreach ($modq as $mod) {
        $status = removeMod($mod['id']);
        if ($status['status'] === 'error') {
            array_push($remove_failed, "{$mod['name']}-{$mod['version']}");
        }
    }
    if ($remove_failed) {
        die('{"status":"error","message":"The following mods could not be deleted; '.implode(',', $remove_failed).'"}');
    }

    if (!$db->commit()) {
        die('{"status":"error","message":"Could not commit changes"}');
    }
    die('{"status":"succ","message":"Mod \''.$_GET['name'].'\' deleted successfully."}');
}
