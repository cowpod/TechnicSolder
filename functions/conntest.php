<?php

/*
TODO: This is insecure.
*/

require_once('sanitize.php');

require_once("db.php");
$db = new Db();

if ($db->test2($_POST['db-type'], $_POST['db-host'], $_POST['db-user'], $_POST['db-pass'], $_POST['db-name'])) {
    echo 'success';
    // return('success');
} else {
    echo 'error';
    // return('error');
}

exit();
