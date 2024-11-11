<?php
define('DEFAULT_PERMS', '0000000');

function isStrongPassword($password) {
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
$config = require("./config.php");

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
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die('{"status":"error","message":"Unauthorized request or login session has expired."}');
}
if ($_SESSION['user']!==$config['mail']) {
    die('{"status":"error","message":"Insufficient permission!"}');
}
if (!isset($config['encrypted'])||$config['encrypted']==false) {
    $pass = $_POST['pass'];
} else {
    // OLD HASHING METHOD (INSECURE)
    // $pass = hash("sha256", $_POST['pass']."Solder.cf");
    $pass = password_hash($_POST['pass'], PASSWORD_DEFAULT);
}

// sanitize name (email)
if (!preg_match('/^[\w\.\-\+]+@[a-zA-Z\d\.-]+\.[a-zA-Z]{2,}$/', $_POST['name'])) {
    die('{"status":"error","message":"Invalid email"}');
}

// sanitize username
if (!ctype_alnum($_POST['display_name'])) {
    die('{"status":"error","message":"Invalid username"}');

}

if ($_POST['name']==$config['mail']) {
    die('{"status":"error","message":"User with that email already exists"}"');
}

require_once("db.php");
$db=new Db;
$db->connect();

// email is stored in name field...
$user_existsq = $db->query("SELECT 1 FROM users WHERE name='".$_POST['name']."'");
if ($user_existsq) {
    die('{"status":"error","message":"User with that email already exists"}"');
}

$icon = "iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAB9ElEQVR4Xu2bSytEcRiHZyJRaDYWRhJilFlYKjakNOWS7OxEGCRGpAg1KykRSlHSKLkO0YyFhSiRIQmbIcVEsnCXW/EJPB/g9Jvt0/8s3t73+b3nnDnmpZWaXxP8dssRm6yL+XTc9OO1Ib+9GWCe60BuyUpEvvDYiNysAqgDNAJygCSoFPi/AoaPwbCvXnRAKKoZc/T7rA/5kasEeV1wEvlJnBf5lM+KfD16mPcAFUAdoBGQA8gSkqBSwOAxmBZ8QQdsOTIwRzsPOae7Iy/w/Op3DvLwZd4zgrYnPJ83Xcp7gAqgDtAIyAFkCUlQKWDwGKzdPeUH//ftmKPz9ePIQ6m1yANufq+QPteK58s6tpHvRZTxHqACqAM0AnIAWkISVAoYOwaf13bQAZn2WSzAQ1EB38/3FyP/9R0jz/K/I/cMxSM3VSTzHqACqAM0AnIAWUISVAoYPAbfe6/RAV07b5ijH/uFyD8Dd8jnejy8R+TwnuG8GsTzpXdJvAeoAOoAjYAcQJaQBJUCBo9B+6sDHfDSUoM5Wm1uQ34Z60YeMzOB3DJygNy5yU+sHGNNvAeoAOoAjYAcQJaQBJUCBo/B7Cr+aMrvnMEctVbx9wCVXbxINboS8Pqu0DnyFDf//2B0o4H3ABVAHaARwD1ADpAElQKGjsE/aSRgFj7BEuwAAAAASUVORK5CYII=";

$sql = $db->execute("INSERT INTO users(`name`,`display_name`,`perms`,`pass`,`icon`) VALUES(
    '".$_POST['name']."',
    '".$_POST['display_name']."',
    '".DEFAULT_PERMS."',
    '".$pass."',
    '".$icon."')");


if ($sql) {
    echo '{"status":"succ","message":"New user created","id":'.$db->insert_id().'}';
} else {
    echo '{"status":"error","message":"An error has occured"}';
}

$db->disconnect();
exit();
