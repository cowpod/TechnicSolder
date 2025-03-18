<?php
session_start();

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired.");
}
// if ((empty($_POST['oldpass'])) {
//     die("Password not specified.");
// }
// if (empty($_POST['display_name'])) {
//     die("Name not specified.");
// }

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
        die('{"status":"error","message":"Could not read image."}');
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

    if (!empty($_POST['oldpass']) && !empty($_POST['pass'])) {
        if (!isStrongPassword($_POST['pass'])) {
            die("Bad password.");
        }

        $verifypwdq = $db->query("SELECT pass FROM users WHERE name = '{$_SESSION['user']}'");
        if (!$verifypwdq) {
            die("Could not verify current password");
        }
        $oldpasswordhash=$verifypwdq[0]['pass'];
        if (!password_verify($_POST['oldpass'], $oldpasswordhash)) {
            error_log("stored password does not match current password");
            die("Could not verify current password");
        }

        $newpass = password_hash($_POST['pass'], PASSWORD_DEFAULT);
        $db->execute("UPDATE users SET pass = '{$newpass}' WHERE name = '{$_SESSION['user']}'");
    }

    if (!empty($_POST['display_name'])) {
        if (!isValidName($_POST['display_name'])) {
            die("Bad name. Only letters, numbers, spaces, hyphens, periods, and underscores allowed.");
        }
        $updatedisplayname = $db->execute("UPDATE users SET display_name = '{$_POST['display_name']}' WHERE name = '{$_SESSION['user']}'");
        if (!$updatedisplayname) {
            die("Could not update name");
        }

        // update session vars. otherwise user will need to relog.
        $_SESSION['name'] = $_POST['display_name'];
    }
}

$db->disconnect();

header("Location: ".$config->get('dir')."account");
exit();