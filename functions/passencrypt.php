<?php
session_start();
$config = require("./config.php");

if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired.");
}
if ($_SESSION['user']!==$config['mail']) {
    die("insufficient permission!");
}

require("db.php");
$db=new Db;

//$sql = $db->query("UPDATE `users` SET `display_name` = '".$_POST['display_name']."', `perms` = '".$_POST['perms']."' WHERE `name` = '".$_POST['name']."'");
if (!isset($config['encrypted'])||$config['encrypted']==false) {
    $db->connect();
    $users = $db->query("SELECT * FROM `users`");
    $db->disconnect();
    foreach ($users as $user) {
        // OLD HASHING METHOD (INSECURE)
        // $db->query("UPDATE `users` SET `pass` = '".hash("sha256",$user['pass']."Solder.cf")."' WHERE `name` = '".$user['name']."'");
        $db->query( "UPDATE `users` SET `pass` = '".password_hash($user['pass'], PASSWORD_DEFAULT)."' WHERE `name` = '".$user['name']."'");
    }
    // OLD HASHING METHOD (INSECURE)
    // $mainpass = hash("sha256",$config['pass']."Solder.cf");
    $mainpass = password_hash($config['pass'], PASSWORD_DEFAULT);
    $cf = "<?php return array( \"configured\" => true, \"author\" => '".$config['author']."', \"mail\" => '".$config['mail']."', \"pass\" => '".$mainpass."', \"db-host\" => '".$config['db-host']."', \"db-user\" => '".$config['db-user']."', \"db-name\" => '".$config['db-name']."', \"db-pass\" => '".$config['db-pass']."', \"host\" => '".$config['host']."', \"dir\" => '".$config['dir']."', \"api_key\" => '".$config['api_key']."', \"encrypted\" => true ";
    file_put_contents("./config.php", $cf." );");
    header("Location: ../");
} else {
    //$pass = hash("sha256",$_POST['pass']."Solder.cf");
    die("Passwords are already encrypted");
}
