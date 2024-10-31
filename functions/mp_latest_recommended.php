<?php
// global $db;
// require_once("db.php");
// if (!isset($db)){
//     $db=new Db;
//     $db->connect();
// }

function mp_latest_recommended($db) {
	$mp_modpack = $db->query("SELECT latest,recommended FROM modpacks WHERE name = '".$db->sanitize($_GET['name'])."'");

	if ($mp_modpack && sizeof($mp_modpack)==1) {
		$mp_modpack=$mp_modpack[0];

		// this doesn't appear to be used anywhere??
	// 	$builds = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$mp_modpack['id']);
	// } else {
	// 	$builds=[];
	}

	// error_log(json_encode($mp_response));
	return json_encode([
		"recommended" => !empty($mp_modpack['recommended']) ? $mp_modpack['recommended'] : null,
		"latest" => !empty($mp_modpack['latest']) ? $mp_modpack['latest'] : null]);
}