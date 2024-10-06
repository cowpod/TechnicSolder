<?php
require("./functions/db.php");
$db=new Db;
$db->connect();

$mp = $db->sanitize($_GET['name']);
$result = $db->query("SELECT * FROM `modpacks` WHERE `name` = '" . $mp . "'");
$modpack = ($result);
$buildsres = $db->query("SELECT * FROM `builds` WHERE `modpack` = " . $modpack['id']);
$builds = [];
foreach($buildsres as $build) {
	array_push($builds, $build['name']);
}
$response = array(
	"recommended" => $modpack['recommended'],
	"latest" => $modpack['latest']
);
return json_encode($response);
exit();