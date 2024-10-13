<?php
header('Content-Type: application/json');
session_start();

if (empty($_GET['id'])) {
    die("Id not specified.");
}
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

// check if a build has the mod
// todo: rewrite builds table! 
// or have a new table with just buildid, modid
$querybuilds = $db->query("SELECT mods FROM builds");
if ($querybuilds&&sizeof($querybuilds)>0) {
    if (!empty($querybuilds[0]['mods'])) {
        $mods=explode(',', $querybuilds[0]['mods']);
    }
}
if (in_array($_GET['id'], $mods)) {
    error_log('{"status":"error","message":"Cannot delete! Mod is in a build."}');
    die('{"status":"error","message":"Cannot delete! Mod is in a build."}');
}

// get filename for mod id (and if it exists)
// $mod;
$modq = $db->query("SELECT type,filename FROM `mods` WHERE `id` = '".$db->sanitize($_GET['id'])."'");
if ($modq) {
    if(sizeof($modq)==0) {
        die('{"status":"error","message":"Specified id does not exist."}');
    }
    $mod = $modq[0];
}

// die('{"status":"succ","message":"Mod deleted from database and disk."}');

// remove it from db
$db->execute("DELETE FROM `mods` WHERE `id` = '".$db->sanitize($_GET['id'])."'");

if ($mod['type']=='mod') {
    // check if theres any other mod entries with the same file
    $mod2q = $db->query("SELECT COUNT(*) as count FROM `mods` WHERE `filename` = '".$mod['filename']."'");
    if ($mod2q && sizeof($mod2q)==1 && $mod2q[0]['count']>0) {
        // only delete from db, leave file on disk
        // error_log('deleted from db, '.JSON_ENCODE($mod2q));
        die('{"status":"succ","message":"Mod deleted from database."}');
    }
}

// delete file from disk since we have no other mods with same filename
// error_log('deleted from db and disk');
unlink("../".$mod['type']."s/".$mod['filename']);
echo '{"status":"succ","message":"Mod deleted from database and disk."}';

exit();
