<?php
session_start();
$config = require("./config.php");

if (empty($_GET['id'])) {
    die("Modpack not specified.");
}
if (empty($_GET['build'])) {
    die("Build not specified.");
}
if (empty($_GET['newname'])) {
    die("New name not specified.");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}

global $db;
require("db.php");
if (!isset($db)){
    $db=new Db;
    $db->connect();
}

$sql = $db->query("SELECT `name` FROM `builds` WHERE `id` = " .$db->sanitize($_GET['build'])
);
$name = ($sql);
echo $name['name'];
if (substr($_SESSION['perms'], 1, 1)!=="1") {
    echo 'Insufficient permission!';
    exit();
}
$db->query("INSERT INTO builds(`name`,`minecraft`,`java`,`mods`,`modpack`)
            SELECT `name`,`minecraft`,`java`,`mods`,`modpack` FROM `builds` WHERE `id` = '".$_GET['build']."'"
);
$lb = ($db->query("SELECT `id` FROM `builds` ORDER BY `id` DESC LIMIT 1"))['id'];
$db->query("UPDATE `builds` SET `name` = '".$db->sanitize($_GET['newname'])."' WHERE `id` = ".$lb
);
$db->query("UPDATE `builds` SET `modpack` = '".$db->sanitize($_GET['id'])."' WHERE `id` = ".$lb
);
$lpq = $db->query("SELECT `name`,`modpack`,`public`
            FROM `builds`
            WHERE `public` = 1 AND `modpack` = ".$db->sanitize($_GET['id'])." ORDER BY `id` DESC"
);
$latest_public = ($lpq);
$db->query("UPDATE `modpacks`
            SET `latest` = '".$latest_public['name']."' WHERE `id` = ".$db->sanitize($_GET['id'])
);
header("Location: ".$config['dir']."modpack?id=".$_GET['id']);
exit();
