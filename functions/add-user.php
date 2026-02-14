<?php
require('../constants.php');
define('ICON_DATA', 'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAB9ElEQVR4Xu2bSytEcRiHZyJRaDYWRhJilFlYKjakNOWS7OxEGCRGpAg1KykRSlHSKLkO0YyFhSiRIQmbIcVEsnCXW/EJPB/g9Jvt0/8s3t73+b3nnDnmpZWaXxP8dssRm6yL+XTc9OO1Ib+9GWCe60BuyUpEvvDYiNysAqgDNAJygCSoFPi/AoaPwbCvXnRAKKoZc/T7rA/5kasEeV1wEvlJnBf5lM+KfD16mPcAFUAdoBGQA8gSkqBSwOAxmBZ8QQdsOTIwRzsPOae7Iy/w/Op3DvLwZd4zgrYnPJ83Xcp7gAqgDtAIyAFkCUlQKWDwGKzdPeUH//ftmKPz9ePIQ6m1yANufq+QPteK58s6tpHvRZTxHqACqAM0AnIAWkISVAoYOwaf13bQAZn2WSzAQ1EB38/3FyP/9R0jz/K/I/cMxSM3VSTzHqACqAM0AnIAWUISVAoYPAbfe6/RAV07b5ijH/uFyD8Dd8jnejy8R+TwnuG8GsTzpXdJvAeoAOoAjYAcQJaQBJUCBo9B+6sDHfDSUoM5Wm1uQ34Z60YeMzOB3DJygNy5yU+sHGNNvAeoAOoAjYAcQJaQBJUCBo/B7Cr+aMrvnMEctVbx9wCVXbxINboS8Pqu0DnyFDf//2B0o4H3ABVAHaARwD1ADpAElQKGjsE/aSRgFj7BEuwAAAAASUVORK5CYII=');

function isStrongPassword($password): bool
{
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

session_start();
if (empty($_SESSION['user'])) {
    die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
}


require_once('./permissions.php');
global $perms;
$perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
if (!$perms->privileged()) {
    die('{"status":"error","message":"Insufficient permission!"}');
}

require_once('./configuration.php');
global $config;
if (empty($config)) {
    $config = new Config();
}

if (empty($_POST['name'])) {
    die('{"status":"error","message":"Email not specified."}');
}
if (empty($_POST['display_name'])) {
    die('{"status":"error","message":"Name not specified."}');
}
if (empty($_POST['pass'])) {
    die('{"status":"error","message":"password not specified."}');
}
if (!isStrongPassword($_POST['pass'])) {
    die('{"status":"error","message":"Bad password."}');
}

$email = strtolower($_POST['name']);
$name = $_POST['display_name'];
// OLD HASHING METHOD (INSECURE)
// $pass = hash("sha256", $_POST['pass']."Solder.cf");
$pass = password_hash($_POST['pass'], PASSWORD_DEFAULT);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('{"status":"error","message":"Malformed email"}');
}
if (!preg_match("/^[a-zA-Z0-9\s\-\.\s]+$/", $name)) {
    die('{"status":"error","message":"Malformed name"}');
}
// if (!preg_match("/^[a-zA-Z0-9+\/]+={,2}$/", $pass)) {
//     die("Bad input data; pass");
// }

require_once("db.php");
$db = new Db();
$db->connect();

if (!$db->beginTransaction(true)) {
    die('{"status":"error","message":"Could not start transaction"}');
}

if($db->query("SELECT 1 FROM users WHERE name='".$email."'")) {
    die('{"status":"error","message":"User with that email already exists"}');
}

if (!$db->execute("INSERT INTO users(`name`,`display_name`,`perms`,`pass`,`icon`) VALUES(
    '".$email."',
    '".$name."',
    '".DEFAULT_PERMS."',
    '".$pass."',
    '".ICON_DATA."')")) {
    die('{"status":"error","message":"An error has occured"}');
}
$id = $db->insert_id();

if (!$db->commit()) {
    die('{"status":"error","message":"Could not commit changes"}');
}

die('{"status":"succ","message":"New user created","id":'.$id.'}');
