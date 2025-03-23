<?php
header('content-type: text/javascript');
session_start();

if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}

function isStrongPassword($password): bool {
    if (strlen($password) < 8) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    return true;
}
function isValidName($name) {
    return preg_match("/^[\w\-\s\.]{2,255}$/", $name);
}

function update_icon($db, $target_user, $iconfile) {
    if (empty($iconfile) ) {
        return ["status"=>"error","message"=>"File missing"];
    }
    $icon = $iconfile;
    if (!file_exists($icon)) {
        return ["status"=>"error","message"=>"File doesn't exist"];
    }
    if (!getimagesize($icon)) {
        return ["status"=>"error","message"=>"Bad image"];
    }
    if (filesize($icon)>15728640) {
        return ["status"=>"error","message"=>"File size too large (max 15MB)"];
    }

    $contents = @file_get_contents($icon);
    if ($contents === FALSE) {
        return ["status"=>"error","message"=>"Could not get image"];
    }
    
    $icon_base64 = base64_encode($contents);

    $updateicon = $db->execute("UPDATE users SET icon = '{$icon_base64}' WHERE name = '{$target_user}'");
    if (!$updateicon) {
        return ["status"=>"error","message"=>"Could not set user icon"];
    }

    $db->disconnect();

    $f = finfo_open();
    $type = finfo_buffer($f, $contents, FILEINFO_MIME_TYPE);

    return ["status"=>"succ","message"=>"Icon updated.","data"=>["type"=>$type,"data"=>$icon_base64]];
}

function update_perms($db,$target_user,$perms) {
    if (empty($perms)) {
        return ["status"=>"error","message"=>"Perms missing"];
    }
    if (empty($target_user)) {
        return ["status"=>"error","message"=>"User missing"];
    }
    if (empty($_SESSION['privileged']) || !$_SESSION['privileged']) {
        return ["status"=>"error","message"=>"Insufficient permission!"];
    }
    if (strspn($perms, '01') != strlen($perms)) {
        return ["status"=>"error","message"=>"Malformed permission data"];
    }
    if (!filter_var($target_user, FILTER_VALIDATE_EMAIL)) {
        return ["status"=>"error","message"=>"Bad input data; name"];
    }

    $setpermsx = $db->execute("UPDATE users SET perms = '{$perms}' WHERE name = '{$target_user}'");
    if (!$setpermsx) {
        return ["status"=>"error","message"=>"Could not update permissions"];
    }
    return ["status"=>"succ","message"=>"Permissions updated."];
    
}

function update_password($db,$target_user,$oldpass,$pass) {
    if (empty($oldpass) || empty($pass)) {
        return ["status"=>"error","message"=>"oldpass / pass missing"];
    }
    if (!isStrongPassword($pass)) {
        return ["status"=>"error","message"=>"Weak password"];
    }

    $verifypwdq = $db->query("SELECT pass FROM users WHERE name = '{$target_user}'");
    if (!$verifypwdq) {
        return ["status"=>"error","message"=>"Could not verify old password"];
    }
    $oldpasswordhash = $verifypwdq[0]['pass'];
    if (!password_verify($oldpass, $oldpasswordhash)) {
        error_log("stored password does not match old password");
        return ["status"=>"error","message"=>"Could not verify old password"];
    }

    $newpass = password_hash($pass, PASSWORD_DEFAULT);
    $setpasswordx = $db->execute("UPDATE users SET pass = '{$newpass}' WHERE name = '{$target_user}'");
    if (!$setpasswordx) {
        return ["status"=>"error","message"=>"Could not update password"];
    }

    return ["status"=>"succ","message"=>"Password updated."];
}

function update_display_name($db,$target_user,$display_name) {
    if (empty($display_name)) {
        return ["status"=>"error","message"=>"display_name missing"];
    }
    if (!isValidName($display_name)) {
        return ["status"=>"error","message"=>"Bad name"];
    }
    $updatedisplayname = $db->execute("UPDATE users SET display_name = '{$display_name}' WHERE name = '{$target_user}'");
    if (!$updatedisplayname) {
        return ["status"=>"error","message"=>"Could not update name"];
    }

    // update session vars. otherwise user will need to relog.
    $_SESSION['name'] = $display_name;

    $idq = $db->query("SELECT id FROM users WHERE name = '{$target_user}'");
    if (empty($idq[0]['id'])) {
        error_log(json_encode($idq));
        return ["status"=>"error","message"=>"Couldn't get user id (database issue?)"];
    }
    $id = $idq[0]['id'];

    return ["status"=>"succ","message"=>"Display name updated.","data"=>["display_name"=>$display_name,"id"=>$id]];
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$return_arr=["status"=>"succ","message"=>""];

// prefer specific-user from _POST over self-user from _SESSION
$target_user = isset($_POST['user']) ? $_POST['user'] : $_SESSION['user'];

// if _SESSION is targetting _POST, validate user is actually admin
if (isset($_POST['user']) && $_POST['user'] != $_SESSION['user']) {
    if (!$_SESSION['privileged']) {
        die("Insuffient permission!");
    }
}

if (!empty($_FILES["newIcon"]["tmp_name"]) 
    && ($ret=update_icon($db, $target_user, $_FILES["newIcon"]["tmp_name"]))) {
    // only overwrite a 'succ' => so an 'error' sticks!
    if ($return_arr['status']=='succ') {
        $return_arr['status'] = $ret['status'];
    }
    $return_arr['message'] .= $ret['message'];
    if (!empty($ret['data'])) {
        $return_arr = array_merge($return_arr, $ret['data']); // append contents of data to return array
    }
}
if (!empty($_SESSION['privileged']) && $_SESSION['privileged'] && !empty($_POST['perms']) && !empty($_POST['user']) 
    && ($ret=update_perms($db, $target_user, $_POST['perms']))) {
    if ($return_arr['status']=='succ') {
        $return_arr['status'] = $ret['status'];
    }
    $return_arr['message'] .= $ret['message'];
    if (!empty($ret['data'])) {
        $return_arr = array_merge($return_arr, $ret['data']); // append contents of data to return array
    }
}
if (!empty($_POST['oldpass']) && !empty($_POST['pass']) 
    && ($ret=update_password($db, $target_user, $_POST['oldpass'], $_POST['pass']))) {
    if ($return_arr['status']=='succ') {
        $return_arr['status'] = $ret['status'];
    }
    $return_arr['message'] .= $ret['message'];
    if (!empty($ret['data'])) {
        $return_arr = array_merge($return_arr, $ret['data']); // append contents of data to return array
    }
}
if (!empty($_POST['display_name']) 
    && ($ret=update_display_name($db, $target_user, $_POST['display_name']))) {
    if ($return_arr['status']=='succ') {
        $return_arr['status'] = $ret['status'];
    }
    $return_arr['message'] .= $ret['message'];
    if (!empty($ret['data'])) {
        $return_arr = array_merge($return_arr, $ret['data']); // append contents of data to return array
    }
}

$db->disconnect();

die(json_encode($return_arr, JSON_UNESCAPED_SLASHES));




