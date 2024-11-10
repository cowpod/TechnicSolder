<?php
define('DEFAULT_PERMS', '0000000');

session_start();
$config = require("./config.php");

if (empty($_POST['name'])) {
    die("Email not specified.");
}
if (empty($_POST['display_name'])) {
    die("Name not specified.");
}
if (empty($_POST['pass'])) {
    die("password not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired.");
}
if ($_SESSION['user']!==$config['mail']) {
    die("insufficient permission!");
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
    die('<span class="text-danger">Invalid email</span>');
}

// sanitize username
if (!ctype_alnum($_POST['display_name'])) {
    die('<span class="text-danger">Invalid username</span>');

}

if ($_POST['name']==$config['mail']) {
    die('<span class="text-danger">User with that email already exists</span>');
}

require_once("db.php");
$db=new Db;
$db->connect();

// email is stored in name field...
$user_existsq = $db->query("SELECT 1 FROM users WHERE name='".$_POST['name']."'");
if ($user_existsq) {
    die('<span class="text-danger">User with that email already exists</span>');
}

$icon = "iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAB9ElEQVR4Xu2bSytEcRiHZyJRaDYWRhJilFlYKjakNOWS7OxEGCRGpAg1KykRSlHSKLkO0YyFhSiRIQmbIcVEsnCXW/EJPB/g9Jvt0/8s3t73+b3nnDnmpZWaXxP8dssRm6yL+XTc9OO1Ib+9GWCe60BuyUpEvvDYiNysAqgDNAJygCSoFPi/AoaPwbCvXnRAKKoZc/T7rA/5kasEeV1wEvlJnBf5lM+KfD16mPcAFUAdoBGQA8gSkqBSwOAxmBZ8QQdsOTIwRzsPOae7Iy/w/Op3DvLwZd4zgrYnPJ83Xcp7gAqgDtAIyAFkCUlQKWDwGKzdPeUH//ftmKPz9ePIQ6m1yANufq+QPteK58s6tpHvRZTxHqACqAM0AnIAWkISVAoYOwaf13bQAZn2WSzAQ1EB38/3FyP/9R0jz/K/I/cMxSM3VSTzHqACqAM0AnIAWUISVAoYPAbfe6/RAV07b5ijH/uFyD8Dd8jnejy8R+TwnuG8GsTzpXdJvAeoAOoAjYAcQJaQBJUCBo9B+6sDHfDSUoM5Wm1uQ34Z60YeMzOB3DJygNy5yU+sHGNNvAeoAOoAjYAcQJaQBJUCBo/B7Cr+aMrvnMEctVbx9wCVXbxINboS8Pqu0DnyFDf//2B0o4H3ABVAHaARwD1ADpAElQKGjsE/aSRgFj7BEuwAAAAASUVORK5CYII=";

$sql = $db->execute("INSERT INTO users(`name`,`display_name`,`perms`,`pass`,`icon`) VALUES(
    '".$_POST['name']."',
    '".$_POST['display_name']."',
    '".DEFAULT_PERMS."',
    '".$pass."',
    '".$icon."')");

$db->disconnect();

if ($sql) {
    echo '<span class="text-success">New user created</span>';
} else {
    echo '<span class="text-danger">An error has occured</span>';
}
exit();
