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

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config=new Config();
}

global $db;
require_once("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

if (!empty($_FILES["newIcon"]["tmp_name"])) {
    $icon = $_FILES["newIcon"]["tmp_name"];
    if (!file_exists($icon)) {
        die('{"status":"error","message":"Uploaded file does not exist"}');
    }
    if (!getimagesize($icon)) {
        die('{"status":"error","message":"Bad image"}');
    }
    if (filesize($icon)>15728640) {
        die('{"status":"error","message":"File size too large (max 15MB)"}');
    }

    $contents = @file_get_contents($icon);
    if ($contents === FALSE) {
        die('{"status":"error","message":"Could not read image"}');
    }
    
    $icon_base64 = base64_encode($contents);

    $updateicon = $db->execute("UPDATE users SET icon = '{$icon_base64}' WHERE name = '{$_SESSION['user']}'");
    if (!$updateicon) {
        die('{"status":"error","Could not set user icon"}');
    }

    $db->disconnect();

    $f = finfo_open();
    $type = finfo_buffer($f, $contents, FILEINFO_MIME_TYPE);
    die('{"status":"succ","message":"Succesfully set user icon","type":"'.$type.'","data":"'.$icon_base64.'"}');
    
} else {
    $displayname_changed = false;
    $password_changed = false;

    if (!empty($_POST['oldpass']) && !empty($_POST['pass'])) {
        if (!isStrongPassword($_POST['pass'])) {
            die('{"status":"error","message":"Bad password"}');
        }

        $verifypwdq = $db->query("SELECT pass FROM users WHERE name = '{$_SESSION['user']}'");
        if (!$verifypwdq) {
            die('{"status":"error","message":"Could not verify old password"}');
        }
        $oldpasswordhash=$verifypwdq[0]['pass'];
        if (!password_verify($_POST['oldpass'], $oldpasswordhash)) {
            error_log("stored password does not match old password");
            die('{"status":"error","message":"Could not verify old password"}');
        }

        $newpass = password_hash($_POST['pass'], PASSWORD_DEFAULT);
        $db->execute("UPDATE users SET pass = '{$newpass}' WHERE name = '{$_SESSION['user']}'");
        $password_changed = true;
    }

    if (!empty($_POST['display_name'])) {
        if (!isValidName($_POST['display_name'])) {
            die('{"status":"error","message":"Bad name"}');
        }
        $updatedisplayname = $db->execute("UPDATE users SET display_name = '{$_POST['display_name']}' WHERE name = '{$_SESSION['user']}'");
        if (!$updatedisplayname) {
            die('{"status":"error","message":"Could not update name"}');
        }

        // update session vars. otherwise user will need to relog.
        $_SESSION['name'] = $_POST['display_name'];
        $displayname_changed = true;
    }

    $db->disconnect();

    if ($displayname_changed && $password_changed) {
        die('{"status":"succ","message":"name and password changed", "name":"'.$_SESSION['name'].'"}');
    } else if ($displayname_changed) {
        die('{"status":"succ","message":"name changed", "name":"'.$_SESSION['name'].'"}');
    } else if ($password_changed) {
        die('{"status":"succ","message":"password changed"}');
    }
echo "done";
}

