<?php
header('Content-Type: application/json');
if (isset($_GET['slug'])&&isset($_GET['build'])) {
	echo file_get_contents("http://api.technicpack.net/modpack/".$_GET['slug']."?build=".$_GET['build']);
}