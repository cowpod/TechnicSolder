<?php
require_once("./functions/db.php");
$db=new Db;
$db->connect();

$mp = $db->sanitize($_GET['name']);

$modpack = $db->query("SELECT * FROM `modpacks` WHERE `name` = '".$mp."'");

if ($modpack && sizeof($modpack)==1) {
	$modpack=$modpack[0];

	// this doesn't appear to be used anywhere??
// 	$builds = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$modpack['id']);
// } else {
// 	$builds=[];
}

// error_log(json_encode($modpack));

$response = array(
	"recommended" => !empty($modpack['recommended']) ? $modpack['recommended'] : null,
	"latest" => !empty($modpack['latest']) ? $modpack['latest'] : null
);

// error_log(json_encode($response));
return json_encode($response);
exit();