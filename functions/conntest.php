<?php
require_once("db.php");
$db=new Db;

if ($db->test2($_POST['db-type'], $_POST['db-host'], $_POST['db-user'], $_POST['db-pass'], $_POST['db-name'])) {
	print('success');
	return('success');
} else {
	print('error');
	return('error');
}
