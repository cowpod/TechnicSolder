<?php
header('Content-Type: application/json');
if (!isset($_GET['slug'])||!isset($_GET['build'])) {
	die("{}");
}
if (!preg_match('/[\w\-_]+/',$_GET['slug'])||!preg_match('/[\w\.\-_]+/',$_GET['build'])) {
	die("{}");
}

@$data=file_get_contents("http://api.technicpack.net/modpack/".$_GET['slug']."?build=".$_GET['build']);
if ($data) {
	die($data);
} else {
	die("{}");
}
