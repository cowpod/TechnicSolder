<?php
// error_reporting(0);
header('Content-Type: application/json');
if (isset($_GET['link'])) {
	echo file_get_contents($_GET['link']);
}

 exit();