<?php
session_start();

define('SUPPORTED_JAVA_VERSIONS', [21,20,19,18,17,16,15,14,13,12,11,1.8,1.7,1.6]);
define('SOLDER_BUILD', '999');

if (file_exists('./functions/config.php')) {
    $config = include("./functions/config.php");
} else {
    $config = ['configured'=>false, 'dir'=>''];
}

// regardless of config.php existing, configured=>false forces a re-configure.
if ($config['configured']!==true) {
    header("Location: ".$config['dir']."configure.php");
    exit();
}

require_once("./functions/db.php");
$db = new Db;
if ($db->connect()===FALSE) {
    die("Couldn't connect to database!");
}

$url = $_SERVER['REQUEST_URI'];
$SERVER_PROTOCOL=strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL']))).'://';

require('functions/interval_range_utils.php');
require('functions/format_number.php');

require('functions/mp_latest_recommended.php');

function uri($uri) {
    global $url;
    $length = strlen($uri);
    if ($length == 0) {
        return true;
    }
    return (substr($url, -$length) === $uri);
}

if (strpos($url, '?') !== false) {
    $url = substr($url, 0, strpos($url, "?"));
}

if (substr($url,-1)=="/") {
    if ($_SERVER['QUERY_STRING']!=="") {
        header("Location: " . rtrim($url,'/') . "?" . $_SERVER['QUERY_STRING']);
    } else {
        header("Location: " . rtrim($url,'/'));
    }
}
if (isset($_GET['logout']) && $_GET['logout']) {
    session_destroy();
    header("Location: ".$config['dir']."login");
    exit();
}

$user=[];
$modslist=[];

if (!empty($_POST['email']) && !empty($_POST['password'])) {
    $userq = $db->query("SELECT * FROM users WHERE name = '". addslashes($_POST['email']) ."' LIMIT 1");
    if ($userq && sizeof($userq)==1) {
        $user = $userq[0];
        if (password_verify($_POST['password'], $user['pass'])) {
        // OLD PASSWORD AUTH METHOD (INSECURE):
        //if ($user['pass']==hash("sha256",$_POST['password']."Solder.cf")) {
            $_SESSION['user'] = $_POST['email'];
            $_SESSION['name'] = $user['display_name'];
            $_SESSION['perms'] = $user['perms'];
            $_SESSION['privileged'] = ($user['privileged']=='1') ? TRUE : FALSE;
        } else {
            header("Location: ".$config['dir']."login?ic");
            exit();
        }
    } else {
        header("Location: ".$config['dir']."login?ic");
        exit();
    }
        
}

if (isset($_SESSION['user']) && (uri("/login")||uri("/"))) {
    header("Location: ".$config['dir']."dashboard");
    exit();
}
if (!isset($_SESSION['user'])&&!uri("/login")) {
    header("Location: ".$config['dir']."login");
    exit();
}

require("functions/user-settings.php");

// set user-settings in session from db
if (isset($_SESSION['user'])) {
    if (!got_settings()) {
        read_settings($_SESSION['user']); // puts them in $_SESSION['user-settings']
    }
}

if (isset($_SESSION['user'])) {
    if (isset($_GET['dark'])) {
        set_setting('dark','on');
    } 
    elseif (isset($_GET['light']) || !get_setting('dark')) {
        set_setting('dark','off');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <link rel="icon" href="./resources/wrenchIcon.png" type="image/png" />
        <title>Technic Solder</title>
        <?php if (get_setting('dark')=="on") {
            echo '<link rel="stylesheet" href="./resources/bootstrap/dark/bootstrap.min.css">';
        } else {
            echo '<link rel="stylesheet" href="./resources/bootstrap/bootstrap.min.css">';
        } ?>
        <script src="./resources/js/jquery.min.js"></script>
        <script src="./resources/js/popper.min.js"></script>
        <script src="./resources/js/fontawesome.js"></script>
        <script src="./resources/js/global.js"></script>
        <script src="./resources/js/marked.min.js"></script>
        <script src="./resources/bootstrap/bootstrap.min.js"></script>
        <script src="./resources/bootstrap/bootstrap-sortable.js"></script>
        <link rel="stylesheet" href="./resources/bootstrap/bootstrap-sortable.css" type="text/css">
        <link rel="stylesheet" href="./resources/css/global.css" type="text/css">
        <style type="text/css">
            .menu-bars {
                vertical-align: middle;
                font-size: 34px;
                color: #2789C9;
                display: none;
                cursor: pointer;
            }
            .nav-tabs .nav-link {
                border: 1px solid transparent;
                border-top-left-radius: .25rem;
                border-top-right-radius: .25rem;
                background: #2F363F;
            }
            .nav-tabs .nav-link.active {
                color: white;
                background-color:#3E4956 !important;
                border-color: transparent !important;
            }
            .nav-tabs .nav-link {
                border: 1px solid transparent;
                border-top-left-radius: 0rem!important;
                border-top-right-radius: 0rem!important;
                width: 100%;
                padding:12px;
                overflow: auto;
                color:#4F5F6D;
            }
            .tab-content>.active {
                display: block;
                min-height: 165px;
                overflow: auto;
            }
            .nav-tabs .nav-item {
                width:102%
            }
            .nav-tabs .nav-link:hover {
                border:1px solid transparent;
                color: white;
            }
            .nav.nav-tabs {
                float: left;
                display: block;
                border-bottom: 0;
                border-right: 1px solid transparent;
                background-color: #2F363F;
            }
            .tab-content .tab-pane .text-muted {
                padding-left: 4em;
                margin:1em;
            }
            .modpack {
                color:white;
                overflow:hidden;
                cursor: pointer;
                white-space: nowrap;
            }
            .modpack p {
                margin:16px;
            }
            .modpack:hover {
                background-color: #2776B3;
            }
            ::-webkit-scrollbar {
                width: 10px;
                height:10px;
            }
            ::-webkit-scrollbar-thumb {
                background: #2776B3;
            }

            ::-webkit-scrollbar-thumb:hover {
                background: #1766A3;
            }
            .info-versions .nav-item{
                margin:5px;
            }
            a:hover {
                text-decoration: none;
            }
            .main {
                margin:2em;
                margin-left: 22em;
                transition: 0.5s;
            }
            .card {
                 padding: 2em;
                 margin: 2em 0;
            }
            .upload-mods {
                border-radius: 5px;
                width: 100%;
                height: 15em;
                background-color: <?php if (get_setting('dark')=="on") {echo "#333";}else {echo "#ddd";} ?>;

                transition: 0.2s;
            }
            .upload-mods input{
                width: 100%;
                height: 100%;
                position:absolute;
                top:0px;
                left:0px;
                opacity: 0.0001;
                cursor: pointer;
            }
            .upload-mods center {
                position: absolute;
                width: 20em;
                top: 8em;
                left: calc( 50% - 10em );
            }
            .upload-mods:hover{
                background-color: <?php if (get_setting('dark')=="on") {echo "#444";}else {echo "#ccc";} ?>;
            }
            .sidenav {
                width:20em;
                height: 100%;
                position:fixed;
                background-color: #3E4956;
                z-index: 1050;
                transition: 0.5s;
            }
            #logindiv {
                width:25em;
                margin:auto;
                margin-top:15em;
                padding:0px
            }
            .d-icon {
                font-size: 0.5em;
                vertical-align: middle;
                margin-right: 0.5em;
            }
            .w-text {
                vertical-align: middle;
            }
            @media only screen and (min-width: 1001px) {
                #logoutside {
                    display:none;
                }
            }
            @media only screen and (min-width: 1251px), (max-width: 780px) {
                .w-sm {
                    display: none;
                }
            }
            @media only screen and (max-width: 780px) {
                .row {
                    display: block;
                }
                .col-4 {
                    max-width: unset;
                }
            }

            @media only screen and (max-width: 1250px) and (min-width: 781px) {
                .w-lg {
                    display: none;
                }
            }
            @media only screen and (max-width: 1500px) and (min-width: 781px), (max-width: 450px) {
                .d-icon {
                    display: none;
                }
            }
            @media only screen and (max-width: 1000px) {
                .menu-bars {
                    display: inline-block;
                }
                #logindiv {
                    width: auto;
                    margin-top:5em;
                }
                #techniclogo {
                    display: none!important;
                }
                #dropdownMenuButton, #solderinfo, #welcome {
                    display: none;
                }
                .sidenav {
                    margin-left: -20em;
                    transition: 0.5s;
                }
                .sidenavexpand {
                    margin-left: 0em;
                    transition: 0.5s;
                }
                .main {
                    margin-left: 2em;
                    transition: 0.5s;
                }
                #logoutside {
                    display:block;
                }
            }
            <?php if (get_setting('dark')=="on") {?>
            .custom-file-label::after {
                background-color: #df691a;
            }
            table.sortable>thead th:hover:not([data-defaultsort=disabled]) {
                background-color:#2E3D4C;
            }
            .form-control:disabled, .form-control[readonly] {
                background-color: rgba(255,255,255,0.5);
            }
        <?php } ?>
        </style>
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body style="<?php if (get_setting('dark')=="on") { echo "background-color: #202429";} else { echo "background-color: #f0f4f9";} ?>">
        <?php
        // prompt to upgrade
        // you'll want to check config_version value to determine what to do.
        if (empty($config['config_version'])) { ?>
        <div class="container">
            <div class="alert alert-danger text-center">
                <h2>Upgrade required.</h2>
                <a href="./functions/upgrade1.3.5to1.4.0.php">Click here to upgrade</a>
            </div>
        </div>
        <?php 
        } elseif (uri("login")) {
        ?>
        <div class="container">
            <div id="logindiv">
                <img style="margin:auto;display:block" alt="Technic logo" height="80" src="./resources/wrenchIcon.svg">
                <legend style="text-align:center;margin:1em 0px">Technic Solder</legend>
                <form method="POST" action="dashboard">
                    <?php if (isset($_GET['ic'])) { ?>
                        <div class="alert alert-danger">
                            Invalid Username/Password
                        </div>
                    <?php } ?>
                    <input style="margin:1em 0px;text-align:center" class="form-control form-control-lg" type="email" name="email" placeholder="Email Address"/>
                    <input style="margin:1em 0px;text-align:center" class="form-control form-control-lg" type="password" name="password" placeholder="Password"/>
                    <button style="margin:1em 0px" class="btn btn-primary btn-lg btn-block" type="submit">Log In</button>
                </form>
            </div>
        </div>
        <?php
        } else {
            $api_version_json = file_get_contents('./api/version.json');
        ?>
        <nav class="navbar <?php if (get_setting('dark')=="on") { echo "navbar-dark bg-dark sticky-top";}else { echo "navbar-light bg-white sticky-top";}?>">
            <span class="navbar-brand"  href="#"><img id="techniclogo" alt="Technic logo" class="d-inline-block align-top" height="46px" src="./resources/wrenchIcon<?php if (get_setting('dark')=="on") {echo "W";}?>.svg"><em id="menuopen" class="fas fa-bars menu-bars"></em> Technic Solder <span id="solderinfo"><?php echo(json_decode($api_version_json,true))['version']; ?></span></span></span>
            <span style="cursor: pointer;" class="dropdown-toggle" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img class="img-thumbnail" style="width: 40px;height: 40px" src="data:image/png;base64,<?php
                    $iconq = $db->query("SELECT `icon` FROM `users` WHERE `name` = '".$_SESSION['user']."'");
                    if ($iconq && sizeof($iconq)>=1 && isset($iconq[0]['icon'])) {
                        echo $iconq[0]['icon'];
                    } else {
                        error_log("failed to get icon for name='".$_SESSION['user']."'");
                    }
                ?>">

                <span class="navbar-text"><?php echo $_SESSION['name'] ?> </span>
                <div style="left: unset;right: 2px;" class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                    <a class="dropdown-item" href="?logout=true&logout=true" onclick="window.location = window.location+'?logout=true&logout=true'">Log Out</a>
                    <a class="dropdown-item" href="./account" onclick="window.location = './account'">My account</a>
                </div>
            </span>
        </nav>
        <div id="sidenav" class="text-white sidenav">
            <ul class="nav nav-tabs" style="height:100%">
                <li class="nav-item">
                    <a class="nav-link " href="./dashboard"><em class="fas fa-tachometer-alt fa-lg"></em></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="#modpacks" data-toggle="tab" role="tab"><em class="fas fa-boxes fa-lg"></em></a>
                </li>
                <li class="nav-item">
                    <a id="nav-mods" class="nav-link" href="#mods" data-toggle="tab" role="tab"><em class="fas fa-book fa-lg"></em></a>
                </li>
                <li class="nav-item">
                    <a id="nav-settings" class="nav-link" href="#settings" data-toggle="tab" role="tab"><em class="fas fa-sliders-h fa-lg"></em></a>
                </li>
                <div style="position:absolute;bottom:5em;left:4em;" class="custom-control custom-switch">
                    <input <?php if (get_setting('dark')=="on"){echo "checked";} ?> type="checkbox" class="custom-control-input" name="dark" id="dark">
                    <label class="custom-control-label" for="dark">Dark theme</label>
                </div>
            </ul>
            <div class="tab-content">
                <div class="tab-pane active" id="modpacks" role="tabpanel">
                    <div style="overflow:auto;height: calc( 100% - 62px )">
                        <p class="text-muted">MODPACKS</p>
                        <?php
                        $modpacksq = $db->query("SELECT * FROM `modpacks`");
                        $totaldownloads=0;
                        $totalruns=0;
                        $totallikes=0;

                        foreach($modpacksq as $modpack) {
                            if (empty($modpack['name'])) {
                                continue;
                            }

                            $notechnic = true;
                            if (!str_starts_with($modpack['name'], 'unnamed-modpack-') && get_setting('api_key')) {
                                $cache = [];
                                $cached_info = [];

                                // clean up old cached data
                                $db->execute("DELETE FROM metrics WHERE time_stamp < ".time());

                                $cacheq = $db->query("SELECT info FROM metrics WHERE name = '".$db->sanitize($modpack['name'])."' AND time_stamp > ".time());

                                if ($cacheq && !empty($cacheq[0]) && !empty($cacheq[0]['info'])) {
                                    $info = json_decode(base64_decode($cacheq[0]['info']), true);
                                }
                                elseif (@$info = json_decode(file_get_contents("http://api.technicpack.net/modpack/".$modpack['name']."?build=".SOLDER_BUILD),true)) {
                                    $time = time()+1800;
                                    $info_data = base64_encode(json_encode($info));

                                    if (!empty($config['db-type']) && $config['db-type']=='sqlite') {
                                        $db->execute("
                                            INSERT OR REPLACE INTO metrics (name,time_stamp,info)
                                            VALUES (
                                                '".$db->sanitize($modpack['name'])."',
                                                ".$time.",
                                                '".$info_data."'
                                        )");
                                    } else {
                                        $db->execute("
                                            REPLACE INTO metrics (name,time_stamp,info)
                                            VALUES (
                                                '".$db->sanitize($modpack['name'])."',
                                                ".$time.",
                                                '".$info_data."'
                                        )");
                                    }
                                }
                                $notechnic = false;
                            }
                            
                            $info_icon = $modpack['icon'];

                            if ((empty($info['installs']) && empty($info['runs']) && empty($info['ratings']))) {
                                $info=['installs'=>0,'runs'=>0,'ratings'=>0];
                            }
                            $totaldownloads += $info['installs'];
                            $totalruns += $info['runs'];
                            $totallikes += $info['ratings'];

                        ?><a href="./modpack?id=<?php echo $modpack['id'] ?>">
                            <div class="modpack">
                                <p class="text-white"><img alt="<?php echo $modpack['display_name'] ?>" class="d-inline-block align-top" height="25px" src="<?php echo $info_icon; ?>"> <?php echo $modpack['display_name'] ?></p>
                            </div>
                        </a><?php

                        }
                        ?>
                        <?php if (substr($_SESSION['perms'],0,1)=="1") { ?>
                        <a href="./functions/new-modpack.php"><div class="modpack">
                            <p><em style="height:25px" class="d-inline-block align-top fas fa-plus-circle"></em> Add Modpack</p>
                        </div></a>
                    <?php } ?>
                    </div>
                </div>
                <div class="tab-pane" id="mods" role="tabpanel">
                    <p class="text-muted">LIBRARIES</p>
                    <a href="./lib-mods"><div class="modpack">
                        <p><em class="fas fa-cubes fa-lg"></em> <span style="margin-left:inherit;">Mod library</span></p>
                    </div></a>
                    <a href="./modloaders"><div class="modpack">
                        <p><em class="fas fa-database fa-lg"></em> <span style="margin-left:inherit;">Mod loaders</span> </p>
                    </div></a>
                    <a href="./lib-others"><div class="modpack">
                        <p><em class="far fa-file-archive fa-lg"></em> <span style="margin-left:inherit;">Other files</span></p>
                    </div></a>
                </div>
                <div class="tab-pane" id="settings" role="tabpanel">
                    <div style="overflow:auto;height: calc( 100% - 62px )">
                        <p class="text-muted">SETTINGS</p>
                    <?php if ($_SESSION['privileged']) { ?>
                        <a href="./admin"><div class="modpack">
                            <p><em class="fas fa-user-tie fa-lg"></em> <span style="margin-left:inherit;">Admin & Server Settings</span></p>
                        </div></a>
                    <?php } ?>
                        <a href="./account"><div class="modpack">
                            <p><em class="fas fa-cog fa-lg"></em> <span style="margin-left:inherit;">My Account</span></p>
                        </div></a>
                    <?php if (substr($_SESSION['perms'],6,1)=="1") { ?>
                        <a href="./clients"><div class="modpack">
                            <p><em class="fas fa-users fa-lg"></em><span style="margin-left:inherit;">Clients</span></p>
                        </div></a>
                        <?php } ?>
                        <a href="./about"><div class="modpack">
                            <p><em class="fas fa-info-circle fa-lg"></em> <span style="margin-left:inherit;">About Solder.cf</span></p>
                        </div></a>
                        <a href="./update"><div class="modpack">
                            <p><em class="fas fa-arrow-alt-circle-up fa-lg"></em> <span style="margin-left:inherit;">Update</span></p>
                        </div></a>
                        <a style="margin-bottom: 3em;" href="?logout=true&logout=true" id="logoutside"><div class="modpack">
                            <p><em class="fas fa-sign-out-alt fa-lg"></em> <span style="margin-left:inherit;">Logout</span></p>
                        </div></a>
                    </div>
                </div>
            </div>
            <script src="./resources/js/page_login.js"></script>
        </div>

        <!-- script for all uris -->
        <script>
            const SOLDER_BUILD = "<?php echo SOLDER_BUILD ?>";
        </script>

        <?php
        if (uri("/dashboard")){
            ?>
            <script>document.title = 'Solder.cf - Dashboard - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <div class="main">
                <?php
                $version = json_decode(file_get_contents("./api/version.json"),true);
                if ($version['stream']=="Dev"||(isset($config['dev_builds']) && $config['dev_builds']=="on")) {
                    if ($newversion = json_decode(file_get_contents("https://raw.githubusercontent.com/TheGameSpider/TechnicSolder/Dev/api/version.json"),true)) {
                        $checked = true;
                    } else {
                        $checked = false;
                        $newversion = $version;
                    }
                } else {
                    if ($newversion = json_decode(file_get_contents("https://raw.githubusercontent.com/TheGameSpider/TechnicSolder/master/api/version.json"),true)) {
                        $checked = true;
                    } else {
                        $checked = false;
                        $newversion = $version;
                    }
                }
                if (version_compare($newversion['version'], $version['version'], '>')) {
                ?>
                <div class="card alert-info <?php if (get_setting('dark')=="on"){echo "text-white";} ?>">
                    <p>Version <strong><?php echo $newversion['version'] ?></strong> is now available!</p>
                    <p><?php echo $newversion['ltcl']; ?></p>
                </div>
            <?php }
            if (!$checked) {
                ?>
                <div class="card alert-warning">
                    <strong>Warning! </strong>Cannot check for updates!
                </div>
                <?php
            }
            if (isset($notechnic) && $notechnic) {
            ?>
                <div class="card alert-warning">
                    <strong>Warning! </strong>Cannot connect to Technic! Verify you set your slug and API key in your <a href="/account">Account Settings</a>.
                </div>
                <?php
            } else {
                $num_downloads=number_suffix_string($totaldownloads);
                $downloadsbig = $num_downloads['big'];
                $downloadssmall = $num_downloads['small'];

                $num_runs=number_suffix_string($totalruns);
                $runsbig = $num_runs['big'];
                $runssmall = $num_runs['small'];

                $num_likes=number_suffix_string($totallikes);
                $likesbig = $num_likes['big'];
                $likessmall = $num_likes['small'];

                ?>
                    <div style="margin-left: 0;margin-right: 0" class="row">
                        <div class="col-4">
                            <div class="card text-white bg-success" style="padding: 0">
                                <div class="card-header">Total Runs</div>
                                <div class="card-body">
                                    <center>
                                        <h1 class="display-2 w-lg"><em class="fas fa-play d-icon"></em><span class="w-text"><?php echo $runsbig ?></span></h1>
                                        <h1 class="display-4 w-sm"><em class="fas fa-play d-icon"></em><?php echo $runssmall ?></h1>
                                    </center>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card bg-info text-white" style="padding: 0">
                                <div class="card-header">Total Downloads</div>
                                <div class="card-body">
                                    <center>
                                        <h1 class="display-2 w-lg"><em class="d-icon fas fa-download"></em><span class="w-text"><?php echo $downloadsbig ?></span></h1>
                                        <h1 class="display-4 w-sm"><em class="d-icon fas fa-download"></em><?php echo $downloadssmall ?></h1>
                                    </center>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card bg-primary text-white" style="padding: 0">
                                <div class="card-header">Total Likes</div>
                                <div class="card-body">
                                    <center>
                                        <h1 class="display-2 w-lg"><em class="fas fa-heart d-icon"></em><span class="w-text"><?php echo $likesbig ?></span></h1>
                                        <h1 class="display-4 w-sm"><em class="fas fa-heart d-icon"></em><?php echo $likessmall ?></h1>
                                    </center>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <!--todo: check the compatibility of instant modpack's mods-->
                <div class="card">
                    <center>
                        <p class="display-4"><span id="welcome" >Welcome to </span>Solder<span class="text-muted">.cf</span></p>
                        <p class="display-5">The best Application to create and manage your modpacks.</p>
                    </center>
                    <hr />
                    <?php if (substr($_SESSION['perms'],0,1)=="1" && substr($_SESSION['perms'],1,1)=="1") { ?>
                    <button class="btn btn-success" data-toggle="collapse" href="#collapseMp" role="button" aria-expanded="false" aria-controls="collapseMp">Instant Modpack</button>
                    <div class="collapse" id="collapseMp">
                        <form method="POST" action="./functions/instant-modpack.php">
                            <br>
                            <input autocomplete="off" required id="dn" class="form-control" type="text" name="display_name" placeholder="Modpack name" />
                            <br />
                            <input autocomplete="off" required id="slug" pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$" class="form-control" type="text" name="name" placeholder="Modpack slug (same as on technicpack.net)" />
                            <br />
                            <label for="java">Select java version</label>
                            <select name="java" class="form-control">
                                <?php
                                foreach (SUPPORTED_JAVA_VERSIONS as $jv) {
                                    $selected = isset($user['java']) && $user['java']==$jv ? "selected" : "";
                                    echo "<option value=".$jv." ".$selected.">".$jv."</option>";
                                }
                                ?>
                            </select> <br />
                            <label for="memory">Memory (RAM in MB)</label>
                            <input required class="form-control" type="number" id="memory" name="memory" value="2048" min="1024" max="65536" placeholder="2048" step="512">
                            <br />
                            <label for="versions">Select minecraft version</label>
                            <select required id="versions" name="versions" class="form-control">
                            <?php
                            // select all forge versions
                            $vres = $db->query("SELECT * FROM `mods` WHERE `type` = 'forge'");
                            if (sizeof($vres)!==0) {
                                foreach($vres as $version) {
                                    ?><option <?php if (!empty($modslist)&&$modslist[0]==$version['id']){ echo "selected"; } ?> value="<?php echo $version['id']?>"><?php echo $version['mcversion'] ?> - <?php echo $version['loadertype']?> <?php echo $version['version'] ?></option><?php
                                }
                                echo "</select>";
                            } else {
                                echo "</select>";
                                echo "<div style='display:block' class='invalid-feedback'>There are no versions available. Please fetch versions in the <a href='./modloaders'>Forge Library</a></div>";
                            }
                            ?>
                            <br />
                            <input id="modlist" class="form-control" type="hidden" name="modlist" />
                            <input autocomplete="off" id="modliststr" required readonly class="form-control" type="text" name="modliststr" placeholder="Mods to add" />
                            <br />
                            <input type="submit" id="submit" disabled class="btn btn-primary btn-block" value="Create">
                        </form>
                        <div id="upload-card" class="card">
                            <h2>Upload mods</h2>
                            <div class="card-img-bottom">
                                <form id="modsform" enctype="multipart/form-data">
                                    <div class="upload-mods">
                                        <center>
                                            <div>
                                                <?php
                                                if (substr($_SESSION['perms'],3,1)=="1") {
                                                    echo "
                                                    Drag n' Drop .jar files here.
                                                    <br />
                                                    <em class='fas fa-upload fa-4x'></em>
                                                    ";
                                                } else {
                                                    echo "
                                                    Insufficient permissions!
                                                    <br />
                                                    <em class='fas fa-times fa-4x'></em>
                                                    ";
                                                } ?>
                                            </div>
                                        </center>
                                        <input <?php if (substr($_SESSION['perms'],3,1)!=="1") { echo "disabled"; } ?> type="file" name="fiels" accept=".jar" multiple/>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div style="display: none" id="u-mods" class="card">
                            <h2>Mods</h2>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width:25%" scope="col">Mod</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="table-mods">

                                </tbody>
                            </table>
                            <button id="btn-done" disabled class="btn btn-success btn-block" onclick="againMods();">Add more Mods</button>
                        </div>
                    </div>
                    <br />
                <?php } ?>
                    <a target="_blank" href="https://github.com/TheGameSpider/TechnicSolder/wiki/"><button class="btn btn-secondary btn-block" >Documentation</button></a>
                    <br />
                    <button class="btn btn-secondary" data-toggle="collapse" href="#collapseMigr" role="button" aria-expanded="false" aria-controls="collapseMigr">Database migration</button>
                    <div class="collapse" id="collapseMigr">
                        <br>
                        <p>Fill out this form to migrate data from an existing local original solder v0.8 installation to this installation. <span class="text-danger"><strong>WARNING!</strong> This will rewrite your current Solder.cf database!</span></p>
                        <hr>
                        <div id="dbform">
                            <input type="text" class="form-control" id="orighost" placeholder="Address of the database you want to migrate from (e.g. 127.0.0.1)"><br>
                            <input type="text" class="form-control" id="origdatabase" placeholder="Name of the database"><br>
                            <input autocomplete="off" type="text" class="form-control" id="origname" placeholder="Username for the database"><br>
                            <input autocomplete="off" type="password" class="form-control" id="origpass" placeholder="Password for the database"><br>
                            <button class="btn btn-primary" id="submitdbform">Connect</button>
                        </div>
                        <p id="errtext"></p>
                        <div id="migrating" style="display:none">
                            <input type="text" class="form-control" id="origdir" placeholder="Path to original solder install directory (ex. /var/www/solder)"><br>
                            <button class="btn btn-primary" id="submitmigration">Start Migration</button>
                        </div>
                        <hr>
                        <p>If you are using v1.0.0.rc4 or higher, <a href="./functions/upgrade100rc4.php">Click here</a> to upgrade your database to be compatible with v1.0.0.rc1 or higher, if created in a version lower than v1.0.0.rc1</p>
                        <p>If you are using versions below 1.4.0 (and above 1.0.0rc4), click <a href="./functions/upgrade1.3.5to1.4.0.php">here</a> to upgrade your database.</p>
                    </div>
                    <br />
                    <button class="btn btn-secondary" data-toggle="collapse" href="#collapseAnno" role="button" aria-expanded="false" aria-controls="collapseAnno">Public Announcements</button>
                    <div class="collapse" id="collapseAnno">
                        <?php
                        echo $newversion['warns'];
                        ?>
                    </div>
                    <br />
                    <?php if (isset($config['use_verifier']) && $config['use_verifier']=="on") { ?>
                    <button class="btn btn-secondary" data-toggle="collapse" href="#collapseVerify" role="button" aria-expanded="false" aria-controls="collapseVerify">Solder Verifier</button>
                    <div class="collapse" id="collapseVerify">
                        <br />
                        <div class="input-group">
                            <input autocomplete="off" class="form-control <?php if (get_setting('dark')=="on") {echo "border-primary";}?>" type="text" id="link" placeholder="Modpack slug (same as on technicpack.net)" aria-describedby="search" />
                            <div class="input-group-append">
                                <button class="<?php if (get_setting('dark')=="on") { echo "btn btn-primary";} else { echo "btn btn-outline-secondary";} ?>" onclick="get();" type="button" id="search">Search</button>
                            </div>
                        </div>
                        <!-- <pre class="card border-primary" style="white-space: pre-wrap;width: 100%" id="responseRaw">
                        </pre> -->
                        <h3 id="response-title"></h3>
                        <div id="response" style="width: 100%">
                            <span id="solder">
                            </span>
                            <div id="responseR">
                            </div>
                            <div id="feed">
                            </div>
                        </div>
                    </div>
                <?php } ?>
                </div>
                <script src="./resources/js/page_dashboard.js"></script>
            </div>
            <?php
        }
        elseif (uri('/modpack')){
            $modpack = $db->query("SELECT * FROM `modpacks` WHERE `id` = ".$db->sanitize($_GET['id']));
            if ($modpack) {
                assert(sizeof($modpack)==1);
                $modpack=$modpack[0];

                // todo: get rid of this
                $_GET['name'] = $modpack['name'];
            ?>
            <script>document.title = 'Modpack - <?php echo addslashes($modpack['display_name']) ?> - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <ul class="nav justify-content-end info-versions">
                <li class="nav-item">
                    <a class="nav-link" href="./dashboard"><em class="fas fa-arrow-left fa-lg"></em> <?php echo $modpack['display_name'] ?></a>
                </li>
                <?php
                $packdata = json_decode(mp_latest_recommended($db), true);

                $latest=false;
                if ($packdata['latest']!=null) {
                    $latestres = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$db->sanitize($_GET['id'])." AND `id` = ".$packdata['latest']);
                    if (sizeof($latestres)!==0) {
                        $latest=true;
                        $user = $latestres[0];
                    }
                } else {
                    $user=['id'=>$_GET['id'], 'name'=>'null', 'minecraft'=>'null', 'display_name'=>'null', 'public'=>0];
                }
                ?>
                <li <?php if (!$latest){ echo "style='display:none'"; } ?> id="latest-v-li" class="nav-item">
                    <span class="navbar-text"><em style="color:#2E74B2" class="fas fa-exclamation"></em> Latest: <strong id="latest-name"><?php echo $user['name'] ?></strong></span>
                </li>
                <li <?php if (!$latest){ echo "style='display:none'"; } ?> id="latest-mc-li" class="nav-item">
                    <span class="navbar-text"><?php if (isset($user['minecraft'])){echo "MC: ";} ?><strong id="latest-mc"><?php echo $user['minecraft'] ?></strong></span>
                </li>
                <div style="width:30px"></div>
                    <?php

                $rec=false;
                if ($packdata['recommended']!=null) {
                    $recres = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$db->sanitize($_GET['id'])." AND `id` = '".$packdata['recommended']."'");
                    if (sizeof($recres)!==0) {
                        $rec=true;
                        $user = $recres[0];
                    }
                } else {
                    $user=['id'=>$_GET['id'], 'name'=>'null', 'minecraft'=>'null', 'display_name'=>'null', 'public'=>0];
                }
                ?>
                <li <?php if (!$rec){ echo "style='display:none'"; } ?> id="rec-v-li" class="nav-item">
                    <span class="navbar-text"><em style="color:#329C4E" class="fas fa-check"></em> Recommended: <strong id="rec-name"><?php echo $user['name'] ?></strong></span>
                </li>
                <li <?php if (!$rec){ echo "style='display:none'"; } ?> id="rec-mc-li" class="nav-item">
                    <span class="navbar-text"><?php if (isset($user['minecraft'])){echo "MC: ";} ?><strong id="rec-mc"><?php echo $user['minecraft'] ?></strong></span>
                </li>
                <div style="width:30px"></div>
            </ul>
            <div class="main">
                <?php
                $notechnic=true;
                if (!str_starts_with($modpack['name'], 'unnamed-modpack-') && get_setting('api_key')) {
                    $cache = [];
                    $cached_info = [];

                    // clean up old cached data
                    $db->execute("DELETE FROM metrics WHERE time_stamp < ".time());

                    $cacheq = $db->query("SELECT info FROM metrics WHERE name = '".$db->sanitize($modpack['name'])."' AND time_stamp > ".time());
                    if ($cacheq && !empty($cacheq[0]) && !empty($cacheq[0]['info'])) {
                        $info = json_decode(base64_decode($cacheq[0]['info']),true);
                    }
                    elseif (@$info = json_decode(file_get_contents("http://api.technicpack.net/modpack/".$modpack['name']."?build=".SOLDER_BUILD),true)) {
                        $time = time()+1800;
                        $info_data = base64_encode(json_encode($info));
                        if (!empty($config['db-type']) && $config['db-type']=='sqlite') {
                            $db->execute("
                                INSERT OR REPLACE INTO metrics (name,time_stamp,info)
                                VALUES (
                                    '".$db->sanitize($modpack['name'])."',
                                    ".$time.",
                                    '".$info_data."'
                            )");
                        } else {
                            $db->execute("
                                REPLACE INTO metrics (name,time_stamp,info)
                                VALUES (
                                    '".$db->sanitize($modpack['name'])."',
                                    ".$time.",
                                    '".$info_data."'
                            )");
                        }
                    }
                    $notechnic = false;
                }
                
                if (isset($notechnic) &&$notechnic) {
                    ?><div class="card alert-warning">
                        <strong>Warning! </strong>Cannot connect to Technic! Verify you set your slug and API key in your <a href="/account">Account Settings</a>.
                    </div><?php
                }
                
                if (empty($info['installs']) && empty($info['runs']) && empty($info['ratings'])) {
                    $info=['installs'=>0,'runs'=>0,'ratings'=>0];
                }

                $totaldownloads = $info['installs'];
                $totalruns = $info['runs'];
                $totallikes = $info['ratings'];

                $num_downloads=number_suffix_string($totaldownloads);
                $downloadsbig = $num_downloads['big'];
                $downloadssmall = $num_downloads['small'];

                $num_runs=number_suffix_string($totalruns);
                $runsbig = $num_runs['big'];
                $runssmall = $num_runs['small'];

                $num_likes=number_suffix_string($totallikes);
                $likesbig = $num_likes['big'];
                $likessmall = $num_likes['small'];
                
                if (isset($runsbig)) {
                ?>
                <div style="margin-left: 0;margin-right: 0" class="row">
                    <div class="col-4">
                        <div class="card text-white bg-success" style="padding: 0">
                            <div class="card-header">Runs</div>
                            <div class="card-body">
                                <center>
                                    <h1 class="display-2 w-lg"><em class="fas fa-play d-icon"></em><span class="w-text"><?php echo $runsbig ?></span></h1>
                                    <h1 class="display-4 w-sm"><em class="fas fa-play d-icon"></em><?php echo $runssmall ?></h1>
                                </center>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card bg-info text-white" style="padding: 0">
                            <div class="card-header">Downloads</div>
                            <div class="card-body">
                                <center>
                                    <h1 class="display-2 w-lg"><em class="d-icon fas fa-download"></em><span class="w-text"><?php echo $downloadsbig ?></span></h1>
                                    <h1 class="display-4 w-sm"><em class="d-icon fas fa-download"></em><?php echo $downloadssmall ?></h1>
                                </center>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card bg-primary text-white" style="padding: 0">
                            <div class="card-header">Likes</div>
                            <div class="card-body">
                                <center>
                                    <h1 class="display-2 w-lg"><em class="fas fa-heart d-icon"></em><span class="w-text"><?php echo $likesbig ?></span></h1>
                                    <h1 class="display-4 w-sm"><em class="fas fa-heart d-icon"></em><?php echo $likessmall ?></h1>
                                </center>
                            </div>
                        </div>
                    </div>
                </div>
                <?php } ?>

                <?php
                if (substr($_SESSION['perms'],0,1)=="1") { ?>
                <div class="card">
                    <h2>Edit Modpack</h2>
                    <hr>
                    <form action="./functions/edit-modpack.php" method="">
                        <input hidden type="text" name="id" value="<?php echo $_GET['id'] ?>">
                        <input autocomplete="off" id="dn" class="form-control" type="text" name="display_name" placeholder="Modpack name" value="<?php echo $modpack['display_name'] ?>" />
                        <br />
                        <input autocomplete="off" id="slug" pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$" class="form-control" type="text" name="name" placeholder="Modpack slug (same as on technicpack.net)" value="<?php echo $modpack['name'] ?>" />
                        <br />
                        <div class="custom-control custom-checkbox">
                            <input <?php if ($modpack['public']==1){echo "checked";} ?> type="checkbox" name="ispublic" class="custom-control-input" id="public">
                            <label class="custom-control-label" for="public">Public Modpack</label>
                        </div><br />
                        <div class="btn-group" role="group" aria-label="Actions">
                            <button type="submit" name="type" value="rename" class="btn btn-primary">Save</button>
                            <button data-toggle="modal" data-target="#removeModpack" type="button" class="btn btn-danger">Remove Modpack</button>
                        </div>

                    </form>
                    <div class="modal fade" id="removeModpack" tabindex="-1" role="dialog" aria-labelledby="rmp" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="rmp">Delete modpack <?php echo $modpack['display_name'] ?>?</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            Are you sure you want to delete modpack <?php echo $modpack['display_name'] ?> and all it's builds?
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-dismiss="modal">No</button>
                            <button onclick="window.location='./functions/rmp.php?id=<?php echo $modpack['id'] ?>'" type="button" class="btn btn-danger" data-dismiss="modal">Delete</button>
                          </div>
                        </div>
                      </div>
                    </div>
                </div>
                <?php
                    if (!$modpack['public']) {
                    $clients = $db->query("SELECT * FROM `clients`");
                ?>

                <div class="card">
                    <h2>Allowed clients</h2>
                    <hr>
                    <form method="GET" action="./functions/change-clients.php">
                    <input hidden name="id" value="<?php echo $_GET['id'] ?>">
                    <?php if (sizeof($clients)<1) {
                        ?>
                        <span class="text-danger">There are no clients in the databse. <a href="./clients">You can add them here</a></span>
                        <br />
                        <?php
                    } ?>
                    <?php
                    $clientlist = isset($modpack['clients']) ? explode(',', $modpack['clients']) : [];
                    foreach ($clients as $client) {
                        ?>
                        <div class="custom-control custom-checkbox">
                            <input <?php if (in_array($client['id'],$clientlist)){echo "checked";} ?> type="checkbox" name="client[]" value="<?php echo $client['id'] ?>" class="custom-control-input" id="client-<?php echo $client['id'] ?>">
                            <label class="custom-control-label" for="client-<?php echo $client['id'] ?>"><?php echo $client['name']." (".$client['UUID'].")" ?></label>
                        </div><br />
                        <?php
                    }
                    ?>
                    <?php if (sizeof($clients)>0) { ?> <input class="btn btn-primary" type="submit" name="submit" value="Save"> <?php } ?>
                </form>
                </div>
            <?php }
                } if (substr($_SESSION['perms'],1,1)=="1") { ?>
                <div class="card">
                    <h2>New Build</h2>
                    <hr>
                    <form action="./functions/new-build.php" method="">
                        <input pattern="^[a-zA-Z0-9.-]+$" required id="newbname" autocomplete="off" class="form-control" type="text" name="name" placeholder="Build name (e.g. 1.0) (a-z, A-Z, 0-9, dot and dash)" />
                        <span id="warn_newbname" style="display: none" class="text-danger">Build with this name already exists.</span>
                        <input hidden type="text" name="id" value="<?php echo $_GET['id'] ?>">
                        <br />
                        <div class="btn-group">
                            <button id="create1" type="submit" name="type" value="new" class="btn btn-primary">Create</button>
                        </div>

                    </form><br />
                    <h2>Copy Build</h2>
                    <hr>
                    <form action="./functions/copy-build.php" method="">
                        <input hidden type="text" name="id" value="<?php echo $_GET['id'] ?>">
                        <?php
                            $sbn = array();
                            $allbuildnames = $db->query("SELECT `name` FROM `builds` WHERE `modpack` = ".$modpack['id']);
                            foreach($allbuildnames as $bn) {
                                array_push($sbn, $bn['name']);
                            }
                            $mpab = array();
                            $allbuilds = $db->query("SELECT `id`,`name`,`modpack` FROM `builds`");
                            foreach($allbuilds as $b) {
                                $display_nameq=$db->query("SELECT `display_name` FROM `modpacks` WHERE `id` = ".$b['modpack']);
                                if($display_nameq) {
                                    assert(sizeof($display_nameq)==1);
                                    $display_name=$display_nameq[0]['display_name'];
                                }
                                $ba = array(
                                    "id" => $b['id'],
                                    "name" => $b['name'],
                                    "mpid" =>  $b['modpack'],
                                    "mpname" => $display_name
                                );
                                array_push($mpab, $ba);
                            }
                            $mps = array();
                            $allmps = $db->query("SELECT `id`,`display_name` FROM `modpacks`");
                            foreach($allmps as $mp) {
                                $mpa = array(
                                    "id" => $mp['id'],
                                    "name" => $mp['display_name']
                                );
                                array_push($mps, $mpa);
                            }
                            ?>
                        <select required="" id="mplist" class="form-control">
                            <option value=null>Please select a modpack..</option>
                            <?php
                            foreach ($mps as $pack) {
                                echo "<option value='".$pack['id']."'>".$pack['name']."</option>";
                            }
                            ?>
                        </select>
                        <br />
                        <select id="buildlist" required="" name='build' class="form-control">
                        </select>
                        <br />
                        <input pattern="^[a-zA-Z0-9.-]+$" type="text" name="newname" id="newname" required class="form-control" placeholder="New Build Name">
                        <span id="warn_newname" style="display: none" class="text-danger">Build with this name already exists.</span>
                        <br />
                        <button type="submit" id="copybutton" name="submit" value="copy" class="btn btn-primary">Copy</button> 
                        <button id="copylatestbutton" class="btn btn-primary" onclick="copylatest()">Copy latest</button>
                    </form>
                </div>
            <?php } ?>
                <div class="card">
                    <h2>Builds</h2>
                    <hr>
                    <table class="table sortable table-striped">
                        <thead>
                            <tr>
                                <th style="width:20%" data-defaultsign="AZ" scope="col">Name</th>
                                <th style="width:20%" data-defaultsign="AZ" scope="col">Minecraft</th>
                                <th style="width:20%" data-defaultsign="AZ" scope="col" class="d-none d-md-table-cell">Java</th>
                                <th style="width:5%"  data-defaultsign="_19" scope="col">Mods</th>
                                <th style="width:30%" data-defaultsort="disabled" scope="col"></th>
                                <th style="width:5%"  data-defaultsort="disabled" scope="col"></th>
                            </tr>
                        </thead>
                        <tbody id="table-builds">
                        <?php
                        $users = $db->query("SELECT * FROM `builds` WHERE `modpack` = ".$db->sanitize($_GET['id'])." ORDER BY `id` DESC");
                        foreach($users as $user) { ?>
                            <tr rec="<?php if ($packdata['recommended']==$user['id']){ echo "true"; } else { echo "false"; } ?>" id="b-<?php echo $user['id'] ?>">
                                <td scope="row"><?php echo $user['name'] ?></td>
                                <td><?php echo $user['minecraft'] ?></td>
                                <td class="d-none d-md-table-cell"><?php echo $user['java'] ?></td>
                                <td><?php echo isset($user['mods']) ? count(explode(',', $user['mods'])) : '' ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                    <?php if (substr($_SESSION['perms'],1,1)=="1") { ?> 
                                        <button onclick="edit(<?php echo $user['id'] ?>)" class="btn <?php echo empty($user['minecraft']) ? 'btn-warning' : 'btn-primary'; ?>">Edit</button>
                                        <button onclick="remove_box(<?php echo $user['id'] ?>,'<?php echo $user['name'] ?>')" data-toggle="modal" data-target="#removeModal" class="btn btn-danger">Remove</button> 
                                    <?php } 
                                    if (substr($_SESSION['perms'],2,1)=="1") { ?>
                                        <button bid="<?php echo $user['id'] ?>" id="pub-<?php echo $user['id']?>" class="btn btn-success" onclick="set_public(<?php echo $user['id'] ?>)" style="display:<?php echo (isset($user['minecraft']) && $user['public']!='1')?'block':'none' ?>">Publish</button>
                                        <!-- if public is null then MC version and loader hasn't been set yet-->

                                        <button bid="<?php echo $user['id'] ?>" id="rec-<?php echo $user['id']?>" class="btn btn-success" onclick="set_recommended(<?php echo $user['id'] ?>)" style="display:<?php echo ($packdata['recommended']!=$user['id']&&$user['public']=='1')?'block':'none' ?>">Recommend</button>

                                        <button bid="<?php echo $user['id'] ?>" id="recd-<?php echo $user['id']?>" class="btn btn-success" style="display:<?php echo ($packdata['recommended']==$user['id']&&$user['public']=='1')?'block':'none' ?>" disabled>Recommended</button>
                                    <?php } ?>
                                    </div>
                                </td>
                                <td>
                                    <em id="cog-<?php echo $user['id'] ?>" style="display:none;margin-top: 0.5rem" class="fas fa-cog fa-lg fa-spin"></em>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                    <div class="modal fade" id="removeModal" tabindex="-1" role="dialog" aria-labelledby="removeModalLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="removeModalLabel">Delete build <span id="build-title"></span>?</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            Are you sure you want to delete build <span id="build-text"></span>?
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-dismiss="modal">No</button>
                            <button id="remove-button" onclick="" type="button" class="btn btn-danger" data-dismiss="modal">Delete</button>
                          </div>
                        </div>
                      </div>
                    </div>
                </div>
                <script>
                    var builds = "<?php echo isset($mpab) ? addslashes(json_encode($mpab)) : '' ?>";
                    var sbn = "<?php echo isset($sbn) ? addslashes(json_encode($sbn)) : '' ?>";
                </script>
                <script src="./resources/js/page_modpack.js"></script>
            </div>
            <?php
            }
        }
        elseif (uri('/build')) {
            $user = $db->query("SELECT * FROM `builds` WHERE `id` = ".$db->sanitize($_GET['id']));
            if ($user && sizeof($user)==1) {
                $user = $user[0];
            }
            $modslist = isset($user['mods']) ? explode(',', $user['mods']) : [];
            if (empty($modslist[0])){
                unset($modslist[0]);
            }

            $mpack = $db->query("SELECT * FROM `modpacks` WHERE `id` = ".$user['modpack']);
            if ($mpack) {
                assert(sizeof($mpack)==1);
                $mpack = $mpack[0];
            }
            ?>
            <script>document.title = '<?php echo addslashes($mpack['display_name'])." ".addslashes($user['name'])  ?> - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <ul class="nav justify-content-end info-versions">
                <li class="nav-item">
                    <a class="nav-link" href="./modpack?id=<?php echo $mpack['id'] ?>"><em class="fas fa-arrow-left fa-lg"></em> <?php echo $mpack['display_name'] ?></a>
                </li>
                <li <?php if ($mpack['latest']!=$user['id']){ echo "style='display:none'"; error_log(json_encode($mpack)); } ?> id="latest-v-li" class="nav-item">
                    <span class="navbar-text"><em style="color:#2E74B2" class="fas fa-exclamation"></em> Latest</span>
                </li>
                <div style="width:30px"></div>
                <li <?php if ($mpack['recommended']!=$user['id']){ echo "style='display:none'"; } ?> id="rec-v-li" class="nav-item">
                    <span class="navbar-text"><em style="color:#329C4E" class="fas fa-check"></em> Recommended</span>
                </li>
                <div style="width:30px"></div>
            </ul>
            <div class="main">
                <?php if (substr($_SESSION['perms'],1,1)=="1") { ?>
                <div class="card">
                    <h2>Build <?php echo $user['name'] ?></h2>
                    <hr>
                    <form method="POST" action="./functions/update-build.php">
                        <input type="hidden" name="id" value="<?php echo $_GET['id'] ?>">
                        <label for="versions">Select minecraft version</label>
                        <select id="versions" name="versions" class="form-control">
                            <?php
                            $loadertype='';
                            $vres = $db->query("SELECT * FROM `mods` WHERE `type` = 'forge'");
                            if (sizeof($vres)!==0) {
                                foreach($vres as $version) {
                                    ?><option <?php 
                                    if (sizeof($modslist)>0 && $modslist[0]==$version['id']){ 
                                        $loadertype=$version['loadertype'];
                                        echo "selected"; 
                                    } ?> value="<?php echo $version['id']?>"><?php echo $version['mcversion'] ?> - <?php echo $version['loadertype'] ?> <?php echo $version['version'] ?></option><?php
                                }
                                echo "</select>";
                            } else { ?>
                                </select>
                                "<div style='display:block' class='invalid-feedback'>There are no versions available. Please fetch versions in the <a href='./modloaders'>Forge Library</a></div>
                            <?php }
                            // error_log($loadertype);
                            ?>
                            <input type="text" name="forgec" id="forgec" value="none" hidden required>
                        <br />
                        <label for="java">Select java version</label>
                        <select name="java" class="form-control">
                                <?php
                                foreach (SUPPORTED_JAVA_VERSIONS as $jv) {
                                    $selected = isset($user['java']) && $user['java']==$jv ? "selected" : "";
                                    echo "<option value=".$jv." ".$selected.">".$jv."</option>";
                                }
                                ?>
                        </select> <br />
                        <label for="memory">Memory (RAM in MB)</label>
                        <input class="form-control" type="number" id="memory" name="memory" value="<?php echo $user['memory'] ?>" min="1024" max="65536" placeholder="2048" step="512">
                        <br />
                        <?php if (substr($_SESSION['perms'],2,1)=="1") { ?>
                        <div class="custom-control custom-checkbox">
                            <input <?php if ($user['public']==1) echo "checked" ?> type="checkbox" name="ispublic" class="custom-control-input" id="public">
                            <label class="custom-control-label" for="public">Public Build</label>
                        </div><br />
                        <?php } ?>
                        <div style='display:none' id="wipewarn" class='text-danger'>Build will be wiped.</div>
                        <button type="submit" id="submit-button" class="btn btn-success">Save and Refresh</button>
                    </form>
                    <div class="modal fade" id="editBuild" tabindex="-1" role="dialog" aria-labelledby="rm" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <div class="modal-content border-danger">
                          <div class="modal-header">
                            <h5 class="modal-title" id="rm">Warning!</h5>
                          </div>
                          <div class="modal-body">
                            Some mods may not be compatible. If you change this, modpack may stop working.
                          </div>
                          <div class="modal-footer">
                            <button id="cancel-button" onclick="fnone()" type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button id="change-button" onclick="fchange()" type="button" class="btn btn-primary" data-dismiss="modal">Change</button>
                            <button id="remove-button" onclick="fwipe()" type="button" class="btn btn-danger" data-dismiss="modal">Wipe build and change</button>
                          </div>
                        </div>
                      </div>
                    </div>
                </div>
                <?php if (!$user['public']) {
                    $clients = $db->query("SELECT * FROM `clients`");
                    ?>
                <div class="card">
                    <h2>Allowed clients</h2>
                    <form method="GET" action="./functions/change-clients-build.php">
                    <input hidden name="id" value="<?php echo $_GET['id'] ?>">
                    <?php if (sizeof($clients)<1) {
                        ?>
                        <span class="text-danger">There are no clients in the databse. <a href="./clients">You can add them here</a></span>
                        <br />
                        <?php
                    } ?>
                    <?php
                    $clientlist = isset($user['clients']) ? explode(',', $user['clients']) : [];
                    foreach ($clients as $client) {
                        ?>
                        <div class="custom-control custom-checkbox">
                            <input <?php if (in_array($client['id'],$clientlist)){echo "checked";} ?> type="checkbox" name="client[]" value="<?php echo $client['id'] ?>" class="custom-control-input" id="client-<?php echo $client['id'] ?>">
                            <label class="custom-control-label" for="client-<?php echo $client['id'] ?>"><?php echo $client['name']." (".$client['UUID'].")" ?></label>
                        </div><br />
                        <?php
                    }
                    ?>
                    <?php if (sizeof($clients)>0) { ?> <input class="btn btn-primary" type="submit" name="submit" value="Save"> <?php } ?>
                </form>
                </div>
            <?php } ?>
                <?php } if (!empty($modslist)) { // only empty on new build before mcversion is set?>
                    <div class="card">
                        <h2>Mods in Build <?php echo $user['name'] ?></h2>
                        <table class="table table-striped sortable">
                            <thead>
                                <tr>
                                    <th scope="col" style="width: 40%" data-defaultsign="AZ">Mod Name</th>
                                    <th scope="col" style="width: 25%" data-defaultsign="_19">Version</th>
                                    <th scope="col" style="width: 15%" data-defaultsign="_19" class="d-none d-sm-table-cell">Minecraft</th>
                                    <th scope="col" style="width: 15%" data-defaultsort="disabled"></th>
                                </tr>
                            </thead>
                            <tbody id="mods-in-build">
                                <?php
                                $modsluglist = Array();
                                $build_mod_ids = $modslist;

                                foreach ($build_mod_ids as $build_mod_id) {

                                    // now get the mod details (before we got ALL the mods...)
                                    $modq = $db->query("SELECT * FROM mods WHERE id = '".$build_mod_id."'");
                                    if (!$modq || sizeof($modq)!=1) {
                                        $mod=[];
                                    } else {
                                        $mod = $modq[0];
                                    
                                        array_push($modsluglist, $mod['name']);

                                        // only check game compatibility if it's a mod!
                                        if ($mod['type']=='mod') {
                                            $mcvrange=parse_interval_range($mod['mcversion']);
                                            $min=$mcvrange['min'];
                                            $minInclusivity=$mcvrange['min_inclusivity'];
                                            $max=$mcvrange['max'];
                                            $maxInclusivity=$mcvrange['max_inclusivity'];

                                            $modvq = $db->query("SELECT id,version FROM mods WHERE type = 'mod'");

                                            $userModVersionOK = in_range($mod['mcversion'], $user['minecraft']);
                                        } else {
                                            $userModVersionOK = true;
                                        }
                                    }

                                    ?>
                                <tr <?php if (empty($mod)) { echo 'class="table-danger"'; } elseif (!$userModVersionOK || empty($mod['version'])) { echo 'class="table-warning"'; } ?> id="mod-<?php echo empty($mod) ? 'missing-'.$build_mod_id : $build_mod_id ?>">
                                    <td scope="row"><?php
                                    echo empty($mod)?'MISSING':$mod['pretty_name'];
                                    if (empty($mod)) {
                                        echo '<span id="warn-incompatible-missing-'.$build_mod_id.'">: Mod does not exist! Please remove from this build.</span>';
                                    } elseif(empty($mod['version'])) {
                                        echo '<span id="warn-incompatible-'.$mod['name'].'">: Missing version! You must set it in <a href="modv?id='.$build_mod_id.'" target="_blank">mod details</a>.</span>';
                                    } elseif(empty($mod['mcversion'])) {
                                        echo '<span id="warn-incompatible-'.$mod['name'].'">: Missing mcversion! You must set it in <a href="modv?id='.$build_mod_id.'" target="_blank">mod details</a>.</span>';
                                    } elseif (!$userModVersionOK) {
                                        echo '<span id="warn-incompatible-'.$mod['name'].'">: For Minecraft '.$mod['mcversion'].', you have '.$user['minecraft'].'. May not be compatible!</span>';
                                    }
                                    ?></td>
                                    <td <?php 
                                        if (isset($mod['type']) && $mod['type']=='mod') { 
                                            if (empty($mod['version'])) { 
                                                echo 'class="table-danger" ';
                                            } else {
                                                echo "data-value='{$mod['version']}'";
                                            }
                                        } 
                                    ?>>
                                        <?php 
                                        if (!empty($mod) && $mod['type'] !== "mod") {
                                            echo $mod['version'];
                                        } elseif(empty($mod)) { ?>
                                        <?php } else { ?>
                                            <select class="form-control" onchange="changeversion(this.value,<?php 
                                            echo $mod['id'] 
                                                ?>,'<?php 
                                            echo $mod['name'] 
                                                ?>',<?php 
                                            if (!in_range($mod['mcversion'], $user['minecraft'])){
                                                echo 'false';
                                            } else { 
                                                echo 'true'; 
                                            } 
                                                ?>);" name="bmversions" id="bmversions-<?php 
                                            echo $mod['name'] ?>"><?php

                                            // get versions for mod
                                            $modv = $db->query("SELECT id,version FROM mods WHERE name='".$mod['name']."'");
                                            if($modv && sizeof($modv)>0) {
                                                foreach ($modv as $mv) {
                                                    if ($mv['id'] == $mod['id']) {
                                                        echo "<option selected value='".$mv['id']."'>".$mv['version']."</option>";
                                                    } else {
                                                        echo "<option value='".$mv['id']."'>".$mv['version']."</option>";
                                                    }
                                                }
                                            }
                                        }
                                        ?>
                                        </select>
                                    </td>
                                    <td  class="d-none d-sm-table-cell"><?php
                                        if (!empty($mod)) {
                                            echo $mod['mcversion'];
                                        }
                                    ?></td>
                                    <td><?php
                                        // todo: we should switch type to be 'loader' instead of 'forge' for loaders.
                                        if (substr($_SESSION['perms'], 1, 1)=="1" && (empty($mod) || $mod['type'] == "mod" || $mod['type'] == "other")) {
                                            ?>
                                            <button onclick="remove_mod(<?php echo $build_mod_id ?>)" class="btn btn-danger">
                                                <em class="fas fa-times"></em>
                                            </button>
                                            <?php
                                        }
                                    ?></td>
                                </tr>
                                <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (substr($_SESSION['perms'],1,1)=="1") { ?>
                    <div class="card">
                        <h2>Mods <font id='mods-for-version-string'></font></h2>
                        <hr>
                        <input id="search" type="text" placeholder="Search..." class="form-control">
                        <br />
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="showall" class="custom-control-input" id="showall">
                            <label class="custom-control-label" for="showall">All versions</label>
                        </div>
                        <br />
                        <table id="modstable" class="table table-striped sortable">
                            <thead>
                                <tr>
                                    <th scope="col" style="width: 50%" data-defaultsign="AZ">Mod Name</th>
                                    <th scope="col" style="width: 25%" data-defaultsign="_19">Version</th>
                                    <th scope="col" style="width: 25%" data-defaultsort="disabled"></th>
                                </tr>
                            </thead>
                            <tbody id="build-available-mods">
                            </tbody>
                        </table>
                        <div id='no-mods-available' class='text-center' style='display:none'>There are no mods available for this version. Upload mods in <a href='./lib-mods'>Mod Library</a>.</div>
                        <div id='no-mods-available-search-results' class='text-center' style='display:none'>No mods match your search.</div>
                    </div>
                    <div class="card">
                        <h2>Other Files</h2>
                        <hr>
                        <input id="search" type="text" placeholder="Search..." class="form-control">
                        <table class="table table-striped sortable">
                            <thead>
                                <tr>
                                    <th scope="col" style="width: 40%" data-defaultsign="AZ">File Name</th>
                                    <th scope="col" style="width: 30%" data-defaultsort="disabled"></th>
                                    <th scope="col" style="width: 5%" data-defaultsort="disabled"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $mres = $db->query("SELECT * FROM `mods` WHERE `type` = 'other'");
                            if (sizeof($mres)!==0) {
                                ?>
                                <?php
                                foreach($mres as $mod) {
                                    if (!in_array($mod['id'], $modslist)) {
                                        ?>
                                        <tr>
                                            <td scope="row"><?php echo $mod['pretty_name'] ?></td>
                                            <td><button id="btn-add-o-<?php echo $mod['id'] ?>" onclick="add_o(<?php echo $mod['id'] ?>)" class="btn btn-primary">Add to build</button></td>
                                            <td><em id="cog-o-<?php echo $mod['id'] ?>" style="display:none" class="fas fa-cog fa-spin fa-2x"></em><em id="check-o-<?php echo $mod['id'] ?>" style="display:none" class="text-success fas fa-check fa-2x"></em></td>
                                        </tr>
                                        <?php
                                    }
                                }
                            } else {
                                echo "<div style='display:block' class='invalid-feedback'>There are no files available. Please upload files in <a href='./lib-others'>Files Library</a></div>";
                            } ?>
                            </tbody>
                        </table>
                    </div>
                <?php }
                } else echo "<div class='card'><h3 class='text-info'>Select minecraft version and save before editing mods.</h3></div>"; ?>
                <script>
                    var modslist_0 = JSON.parse('<?php echo json_encode($modslist, JSON_UNESCAPED_SLASHES) ?>');
                    var build_id = '<?php echo $user['id'] ?>';
                    var mcv="<?php echo $user['minecraft'] ?>";
                    var type="<?php echo $user['loadertype'] ?>";
                </script>
                <script src="./resources/js/page_build.js"></script>
            </div>
        <?php
        }
        elseif (uri('/lib-mods')) {
        ?>
        <script>document.title = 'Mod Library - <?php echo addslashes($_SESSION['name']) ?>';</script>
        <div class="main">

            <?php if (substr($_SESSION['perms'],3,1)=="1") { ?>

            <?php if ($config['modrinth_integration']=='on') { ?>
            <div class="card">
                <h2>Get mods - Modrinth</h2>
                <form class="row" action="javascript:void(0)">
                    <div class="col-md-12 col-12 mb-2">
                        <select class="form-control" id="mcv">
                            <?php
                            $querymcvs=$db->query("SELECT * FROM mods WHERE type='forge'");
                            $selected='selected';
                            foreach ($querymcvs as $mcv) {
                                echo "<option mc='{$mcv['mcversion']}' v={$mcv['version']} type={$mcv['loadertype']} {$selected}>{$mcv['mcversion']} - {$mcv['loadertype']}</option>";
                                if ($selected) {
                                    $selected='';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-10 col-12 mb-2">
                        <input type="text" id="searchquery" class="form-control" placeholder="Search for mods">
                    </div>
                    <div class="col-md-2 col-12 mb-2">
                        <input type="submit" id="searchbutton" class="btn btn-primary form-control" disabled value="Search">
                    </div>
                </form>
                <table class="table table-striped sortable">
                    <thead>
                        <tr>
                            <th scope="col" style="width:15%" data-defaultsign="AZ">Name</th>
                            <th scope="col" style="width:15%" data-defaultsign="AZ" class="d-none d-md-table-cell">Category</th>
                            <th scope="col" style="width:35%" data-defaultsign="AZ">Desc.</th>
                            <th scope="col" style="width:10%" data-defaultsign="AZ" class="d-none d-md-table-cell">Author</th>
                            <th scope="col" style="width:10%" data-defaultsort="disabled"></th>
                        </tr>
                    </thead>
                    <tbody id="searchresults">
                    </tbody>
                </table>
                <div id='noresults' class='text-center' style='display:none'>No results</div>
            </div>

            <div class="modal fade" id="description" tabindex="-1" role="dialog" aria-labelledby="description-title" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered modal-fullscreen modal-lg" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="description-title"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body" id="description-body" style="overflow:auto; max-height:75vh;">
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal fade" id="installation" tabindex="-1" role="dialog" aria-labelledby="installation-title" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered modal-fullscreen" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="installation-title"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body" id="installation-body">
                    <select id="installation-versions" class="form-control">
                    </select>
                    <div id="installation-message" style="display:none; text-align: right;">
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button id="installations-cancel" type="button" class="btn btn-primary" data-dismiss="modal">Cancel</button>
                    <button id="installations-button" type="button" class="btn btn-danger" onclick="installmod()">Install</button>
                  </div>
                </div>
              </div>
            </div>

            <?php } ?>

            <div id="upload-card" class="card">
                <h2>Upload mods</h2>
                <div class="card-img-bottom">
                    <form id="modsform" enctype="multipart/form-data">
                        <div class="upload-mods">
                            <center>
                                <div>
                                    Drag n' Drop .jar files here.
                                    <br />
                                    <em class='fas fa-upload fa-4x'></em>
                                </div>
                            </center>
                            <input <?php if (substr($_SESSION['perms'],3,1)!=="1") { echo "disabled"; } ?> type="file" name="fiels" accept=".jar" multiple/>
                        </div>
                    </form>
                </div>
            </div>

            <div style="display: none" id="u-mods" class="card">
                <h2>New Mods</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:25%" scope="col">Mod</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody id="table-mods">
                    </tbody>
                </table>
                <button id="btn-done" disabled class="btn btn-success btn-block" onclick="$('#table-mods').empty();$('#u-mods').hide();$('#upload-card').show()">Upload more</button>
            </div>

            <div class="card">
                <p class="ml-3"><a href="./remote-mod"><em class="fas fa-plus-circle"></em> Add remote mods</a></p>
            </div>
            <?php } ?>

            <div class="card">
                <h2>Available Mods</h2>
                <hr>
                <input placeholder="Search..." type="text" id="search" class="form-control"><br />
                <table id="modstable" class="table table-striped sortable">
                    <thead>
                        <tr>
                            <th style="width:35%" scope="col" data-defaultsign="AZ">Mod name</th>
                            <th style="width:30%" scope="col" data-defaultsign="AZ" class="d-none d-md-table-cell">Author</td>
                            <th style="width:15%" scope="col" data-defaultsign="_19">Versions</td>
                            <th style="width:20%" scope="col" data-defaultsort="disabled"></th>
                        </tr>
                    </thead>
                    <tbody id="table-available-mods">
                        <?php
                        $mods = $db->query("SELECT * FROM `mods` WHERE `type` = 'mod' ORDER BY `id` DESC");
                        if ($mods){
                            $modsi = array();
                            $modslugs = array();
                            foreach ($mods as $mod) {
                                $modversionsq = $db->query("SELECT `version`,`author` FROM `mods` WHERE `type` = 'mod' AND `name` = '".$mod['name']."' ORDER BY `version` DESC");
                                $modversions = array();
                                $modauthors = array();

                                foreach ($modversionsq as $modversionsa) {
                                    array_push($modversions, $modversionsa['version']);
                                    foreach (explode(", ", $modversionsa['author']) as $a) {
                                        if (!in_array($a, $modauthors)) {
                                            array_push($modauthors, $a);
                                        }
                                    }
                                }
                                $modarray = array(
                                    "id" => $mod['id'],
                                    "name" => $mod['name'],
                                    "pretty_name" => $mod['pretty_name'],
                                    "versions" => $modversions,
                                    "author" => $modauthors,
                                    "mcversion" => $mod['mcversion']
                                );

                                if (!in_array($mod['name'], $modslugs)) {
                                    array_push($modslugs, $mod['name']);
                                    array_push($modsi, $modarray);
                                }
                            }
                            foreach ($modsi as $mod) {
                            ?>
                                <tr id="mod-row-<?php echo $mod['name'] ?>">
                                    <td scope="row" <?php if (empty($mod['pretty_name'])) echo 'class="table-danger"' ?>><?php echo empty($mod['pretty_name']) ? "<span class='text-danger'>Unknown</span>" : $mod['pretty_name'] ?></td>
                                    <td <?php if (implode(", ", $mod['author'])=="") echo 'class="table-danger"' ?> class="d-none d-md-table-cell"><?php if (implode(", ", $mod['author'])!=="") { echo implode(", ", $mod['author']); } else { echo "<span class='text-danger'>Unknown</span>"; } ?></td>
                                    <td><?php echo count($mod['versions']); ?></td>
                                    <td>
                                        <?php if (substr($_SESSION['perms'], 4, 1)=="1") { ?>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                            <button onclick="window.location='./mod?id=<?php echo $mod['name'] ?>'" class="btn btn-primary">Edit</button>
                                            <button onclick="remove_box('<?php echo $mod['name'] ?>')" data-toggle="modal" data-target="#removeMod" class="btn btn-danger">Remove</button>
                                        </div>
                                    <?php } ?>
                                    </td>
                                </tr>
                            <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="modal fade" id="removeMod" tabindex="-1" role="dialog" aria-labelledby="rm" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="rm">Delete mod <span id="mod-name-title"></span>?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                    Are you sure you want to delete mod <span id="mod-name"></span>? All the mod's files and versions will be deleted too.
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">No</button>
                    <button id="remove-button-force" type="button" class="btn btn-danger" data-dismiss="modal">Force Delete</button>
                    <button id="remove-button" type="button" class="btn btn-danger" data-dismiss="modal">Delete</button>
                  </div>
                </div>
              </div>
            </div>
            <script src="./resources/js/page_lib-mods.js"></script>
        </div>
        <?php
        } elseif (uri('/remote-mod')) {
        ?>
        <div class="main">
            <?php
            if (isset($_GET['id'])) {
                $mres = $db->query("SELECT * FROM `mods` WHERE `id` = ".$db->sanitize($_GET['id']));
                if ($mres) {
                    assert(sizeof($mres)==1);
                    $mod = $mres[0];
                }
            }
            ?>
            <script>document.title = 'Add Mod - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <div class="card">
                <button onclick="window.location = './lib-mods'" style="width: fit-content;" class="btn btn-primary"><em class="fas fa-arrow-left"></em> Back</button><br />
                <h3>Add Mod</h3>
                <form method="POST" action="./functions/add-modv.php">
                    <input id="pn" required class="form-control" type="text" name="pretty_name" placeholder="Mod name" />
                    <br />
                    <input id="slug" required pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$" class="form-control" type="text" name="name" placeholder="Mod slug" /><br />
                    <textarea class="form-control" type="text" name="description" placeholder="Mod description"></textarea><br />
                    <input required class="form-control" type="text" name="version" placeholder="Mod Version"><br />
                    <input class="form-control" type="text" name="author" id="author-input" placeholder="Mod Author">
                    <br />
                    <input class="form-control" type="url" name="link" id="link-input" placeholder="Mod Website">
                    <br />
                    <input class="form-control" type="url" name="donlink"  id="donlink-input" placeholder="Author's Website">
                    <br />
                    <div class="input-group">
                        <input required class="form-control" type="url" name="url" placeholder="File URL (this must be a .zip file with the right structure)">
                        <div class="input-group-append" style="cursor:pointer"><span class="input-group-text" data-toggle="popover" title="Solder .zip structure" data-content='The .zip file must include a "mods" folder, within which you place the .jar or .zip original mod file. For example, if you are including the bibliocraft mod, you must place the bibliocraft.jar (the name of the file is irrelevant) inside a "mods" directory. Then you must zip the mods directory and upload this .zip file to a server. Paste your direct link to this file here.'><em class="fas fa-question-circle"></em></span></div>
                    </div>
                    <br />
                    <input required class="form-control" type="text" name="md5" placeholder="File md5 Hash"><br />
                    <input required class="form-control" required type="text" name="mcversion" placeholder="Minecraft Version"><br/>
                    <select name="loadertype">
                        <option value="forge">Forge</option>
                        <option value="neoforge">Neoforge</option>
                        <option value="fabric">Fabric</option>
                    </select>
                    <br /><br />
                    <input type="submit" name="submit" value="Save" class="btn btn-success">
                </form>
            </div>
            <script src="./resources/js/page_add-remote.js"></script>
        </div>

        <?php
        } elseif (uri('/modloaders')) {
        ?>
        <script>document.title = 'Forge Versions - <?php echo addslashes($_SESSION['name']) ?>';</script>
        <div class="main">
            <div class="modal fade" id="removeMod" tabindex="-1" role="dialog" aria-labelledby="rm" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="rm">Delete mod <span id="mod-name-title"></span>?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                    Are you sure you want to delete mod <span id="mod-name"></span>? Mod's file will be deleted too.
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">No</button>
                    <button id="remove-button-force" type="button" class="btn btn-danger" data-dismiss="modal">Force Delete</button>
                    <button id="remove-button" type="button" class="btn btn-danger" data-dismiss="modal">Delete</button>
                  </div>
                </div>
              </div>
            </div>

            <?php if (substr($_SESSION['perms'], 5, 1)=="1") { ?>
            <div class="btn-group btn-group-justified btn-block">
                <button id="fetch-forge" onclick="fetch_forges()" class="btn btn-primary mr-1">Show Forge Versions</button>
                <!-- <button disabled id="save" onclick="window.location.reload()" style="display:none;" class="btn btn-success">(Forge) Save and Refresh</button> -->
                <button id="fetch-neoforge" onclick="fetch_neoforge()" class="btn btn-primary mr-1">Show Neoforge Versions</button>
                <button id="fetch-fabric" onclick="fetchfabric()" class="btn btn-primary mr-1">Show Fabric Versions</button>
            </div>
            <span id="info" class="text-danger"></span>
            <div class="card" id="fabrics" style="display:none;">
                <h2>(α) Fabric Installer</h2>
                <form>
                    <label for="lod">Loader Version</label>
                    <select id="lod"></select><br>
                    <label for="ver">Game Version</label>
                    <select id="ver"></select><br>
                    <button id="sub-button" type="button" onclick="download()" class="btn btn-primary">Install</button>
                </form>
            </div>
            <div class="card" id="neoforges" style="display:none;">
                <h2>Neoforge Installer</h2>
                <form>
                    <label for="lod">Version</label>
                    <select id="lod-neoforge" required></select><br>
<!--                     <label for="ver">Game Version</label>
                    <select id="ver-neoforge" required></select><br> -->
                    <button id="sub-button-neoforge" type="button" onclick="download_neoforge()" class="btn btn-primary">Install</button>
                </form>
                <span id="sub-button-neoforge-message" style="display:hidden;"></span>
            </div>
            <div class="card" id="fetched-mods" style="display: none">
                <h2>Available Forge Versions</h2>
                <table class="table table-striped sortable">
                    <thead>
                        <tr>
                            <th scope="col" style="width:10%" data-defaultsign="_19">Minecraft</th>
                            <th scope="col" style="width:15%" data-defaultsign="_19">Forge Version</th>
                            <th scope="col" style="width:55%" data-defaultsign="AZ" class="d-none d-md-table-cell">Link</th>
                            <th scope="col" style="width:15%" data-defaultsort="disabled"></th>
                            <th scope="col" style="width:5%"  data-defaultsort="disabled"></th>
                        </tr>
                    </thead>
                    <tbody id="forge-table">
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>Upload custom mod loader</h2>
                <hr>
                <form action="./functions/custom_modloader.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <input class="form-control" type="text" name="version" placeholder="Loader version" required="">
                        <br />
                        <input class="form-control" type="text" name="mcversion" placeholder="Minecraft Version" required="">
                        <br />
                        <div class="custom-file">
                            <input id="forge" type="file" class="form-control-file" name="file" accept=".jar" required>
                            <label class="custom-file-label" for="forge">Choose modpack.jar file...</label>
                        </div>
                        <br /><br>
                        <select class="form-control" name="type">
                            <option value="forge">Forge</option>
                            <option value="neoforge">Neoforge</option>
                            <option value="fabric">Fabric</option>
                        </select>
                        <br/>
                        <br/>
                        <button type="submit" class="btn btn-primary">Upload</button>
                </div>
                </form>
            </div>
            <?php } ?>

            <div class="card">
                <h2>Mod loaders</h2>
                <?php if (isset($_GET['errfilesize'])) {
                    echo '<span class="text-danger">File is too big! Check your post_max_size (current value '.ini_get('post_max_size').') and upload_max_filesize (current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'</span>';
                } ?>

                <?php if (isset($_GET['succ'])) {
                    echo '<span class="text-success">File has been uploaded.</span>';
                } ?>

                <table class="table table-striped sortable">
                    <thead>
                        <tr>
                            <th scope="col" style="width:35%" data-defaultsign="_19">Minecraft</th>
                            <th scope="col" style="width:30%" data-defaultsign="_19">Version</th>
                            <th scope="col" style="width:20%" data-defaultsign="AZ">Type</th>
                            <th scope="col" style="width:10%" data-defaultsort="disabled"></th>
                            <th scope="col" style="width:5%"  data-defaultsort="disabled"></th>
                        </tr>
                    </thead>
                    <tbody id="forge-available">
                        <?php
                        $mods = $db->query("SELECT * FROM `mods` WHERE `type` = 'forge' ORDER BY `id` DESC");
                        $installed_mc_loaders=[];
                        if ($mods){
                            foreach ($mods as $mod){
                                if ($mod['loadertype']=='fabric') {
                                    // fabric versioning is different
                                    array_push($installed_mc_loaders, $mod['loadertype'].'-'.$mod['mcversion']);
                                } else {
                                    array_push($installed_mc_loaders, $mod['loadertype'].'-'.$mod['version']);
                                }
                            ?>
                            <tr id="mod-row-<?php echo $mod['id'] ?>">
                                <td scope="row"><?php echo $mod['mcversion'] ?></td>
                                <td><?php echo $mod['version'] ?></td>
                                <td><?php echo $mod['loadertype']?></td>
                                <td> <?php if (substr($_SESSION['perms'], 5, 1)=="1") { ?><button  onclick="remove_box(<?php echo $mod['id'].",'".$mod['pretty_name']." ".$mod['version']."'" ?>)" data-toggle="modal" data-target="#removeMod" class="btn btn-danger btn-sm">Remove</button><?php } ?></td>
                                <td><em style="display: none" class="fas fa-cog fa-spin fa-sm"></em></td>
                            </tr>
                            <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <script>
            <?php
            if (!empty($installed_mc_loaders)) {
                echo 'let installed_mc_loaders=JSON.parse(\''.json_encode($installed_mc_loaders).'\');';
            } else {
                echo 'let installed_mc_loaders=[];';
            }
            ?>
            </script>
            <script src="./resources/js/page_modloaders.js"></script>
        </div>
        <?php
        } elseif (uri('/lib-others')) {
        ?>
        <script>document.title = 'Other Files - <?php echo addslashes($_SESSION['name']) ?>';</script>
        <div class="main">
            <?php if (substr($_SESSION['perms'], 3, 1)=="1") { ?>
            <div id="upload-card" class="card">
                <h2>Upload files</h2>
                <div class="card-img-bottom">
                    <form id="modsform" enctype="multipart/form-data">
                        <div class="upload-mods">
                            <center>
                                <div>
                                    Drag n' Drop .zip files here.
                                    <br />
                                    <em class='fas fa-upload fa-4x'></em>
                                </div>
                            </center>
                            <input <?php if (substr($_SESSION['perms'], 3, 1)!=="1") { echo "disabled"; } ?> type="file" name="fiels" accept=".zip" multiple />
                        </div>
                    </form>
                </div>
                <p>These files will be extracted to modpack's root directory. (e.g. Config files, worlds, resource packs....)</p>
            </div>
            <div style="display: none" id="u-mods" class="card">
                <h2>New Files</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:25%" scope="col">File</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody id="table-mods">

                    </tbody>
                </table>
                <button id="btn-done" disabled class="btn btn-success btn-block" onclick="window.location.reload();">Done</button>
            </div>
            <?php } ?>
            <div class="card">
                <h2>Available Files</h2>
                <table class="table table-striped sortable">
                    <thead>
                        <tr>
                            <th scope="col" style="width:65%" data-defaultsign="AZ">File Name</th>
                            <th scope="col" style="width:35%" data-defaultsort="disabled"></th>
                        </tr>
                    </thead>
                    <tbody id="table-mods">
                        <?php
                        $mods = $db->query("SELECT * FROM `mods` WHERE `type` = 'other' ORDER BY `id` DESC");
                        if ($mods){
                            foreach ($mods as $mod) {
                            ?>
                                <tr id="mod-row-<?php echo $mod['id'] ?>">
                                    <td scope="row"><?php echo $mod['pretty_name'] ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                            <button onclick="window.location = './file?id=<?php echo $mod['id'] ?>'" data-toggle="modal" data-target="#infoMod" class="btn btn-primary">Info</button>
                                             <?php if (substr($_SESSION['perms'], 3, 1)=="1") { ?><button onclick="remove_box(<?php echo $mod['id'].",'".$mod['name']."'" ?>)" data-toggle="modal" data-target="#removeMod" class="btn btn-danger">Remove</button><?php } ?>
                                            <button onclick="window.location = '<?php echo $mod['url'] ?>'" class="btn btn-secondary">Download</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="modal fade" id="removeMod" tabindex="-1" role="dialog" aria-labelledby="rm" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="rm">Delete file <span id="mod-name-title"></span>?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                    Are you sure you want to delete file <span id="mod-name"></span>?
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">No</button>
                    <button id="remove-button-force" type="button" class="btn btn-danger" data-dismiss="modal">Force Delete</button>
                    <button id="remove-button" type="button" class="btn btn-danger" data-dismiss="modal">Delete</button>
                  </div>
                </div>
              </div>
            </div>
            <script src="./resources/js/page_lib-others.js"></script>
        </div>
        <?php
        } elseif (uri("/file")) {
            $mres = $db->query("SELECT * FROM `mods` WHERE `id` = '".$db->sanitize($_GET['id'])."'");
            if ($mres) {
                assert(sizeof($mres)==0);
                $file = $mres[0];
            }
            ?>
            <script>document.title = 'File - <?php echo addslashes($file['name']) ?> - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <div class="main">
                <div class="card">
                    <button onclick="window.location = './lib-others'" style="width: fit-content;" class="btn btn-primary">
                        <em class="fas fa-arrow-left"></em> Back
                    </button><br />
                    <h2><?php echo $file['filename'] ?></h2>
                    <hr>
                    <p>MD5: <?php echo $file['md5'] ?><br >
                    Size: <?php echo formatSizeUnits(filesize('./others/'.$file['filename'])) ?></p>
                    <h3>Files:</h3>
                    <pre id="files_ul">
                    </pre>
                    <h4 id="loadingfiles"><em class="fas fa-cog fa-spin"></em> Loading...</h4>
                </div>
                <?php
                $zip = new ZipArchive;
                $paths=[];
                if ($zip->open('./others/'.$file['filename'])===TRUE) {
                    for ($i=0; $i<$zip->numFiles; $i++) {
                        array_push($paths, $zip->getNameIndex($i));
                    }
                    $zip->close();
                }
                echo '<script>var paths = JSON.parse(\''.json_encode($paths).'\');</script>';
                ?>
                <script src="./resources/js/page_file.js"></script>
            </div>
            <?php
        } elseif (uri("/mod")) {
        ?>
        <div class="main">
            <?php
            $mres = $db->query("SELECT * FROM `mods` WHERE `name` = '".$db->sanitize($_GET['id'])."'");
            ?>
            <script>document.title = 'Mod - <?php echo addslashes($_GET['id']) ?> - <?php echo addslashes($_SESSION['name']) ?>';
            </script>
            <div class="card">
                <button onclick="window.location = './lib-mods'" style="width: fit-content;" class="btn btn-primary">
                    <em class="fas fa-arrow-left"></em> Back
                </button><br />
                <h2>Versions</h2>
                <table class="table sortable table-striped">
                    <thead>
                        <tr>
                            <th style="width:20%" data-defaultsort="AZ" scope="col">Version</th>
                            <th style="width:15%" data-defaultsort="AZ" scope="col">Minecraft</th>
                            <th style="width:10%" data-defaultsort="AZ" scope="col">Loader</th>
                            <th style="width:30%" data-defaultsort="AZ" scope="col" class="d-none d-md-table-cell">File</th>
                            <th style="width:25%" scope="col"></th>
                        </tr>
                    </thead>
                    <tbody id="table-mods">
                    <?php
                    foreach ($mres as $mod) {
                        $modpn = $mod['pretty_name'];
                        $modd = $mod['description'];
                        ?>
                        <tr id="mod-row-<?php echo $mod['id'] ?>">
                            <td <?php if (empty($mod['version'])) echo 'class="table-danger"'; ?> scope="row"><?php echo empty($mod['version'])? '<span class="text-danger">Unknown</span>' : $mod['version'] ?></td>
                            <td <?php if (empty($mod['mcversion'])) echo 'class="table-danger"'; ?>><?php echo empty($mod['mcversion'])? '<span class="text-danger">Unknown</span>' : $mod['mcversion'] ?></td>
                            <td <?php if (empty($mod['loadertype'])) echo 'class="table-danger"'; ?>><?php echo empty($mod['loadertype'])? '<span class="text-danger">Unknown</span>' : $mod['loadertype'] ?></td>
                            <td <?php if (empty($mod['filename'])) echo 'class="table-danger"'; ?> class="d-none d-md-table-cell"><?php echo empty($mod['filename'])? '<span class="text-danger">Unknown</span>' : $mod['filename'] ?></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                    <button onclick="window.location = './modv?id=<?php echo $mod['id'] ?>'"
                                            class="btn btn-primary">Edit</button>
                                    <button onclick="remove_box(<?php echo $mod['id'].",'".$mod['version']."','".$mod['filename']."'" ?>)" data-toggle="modal" data-target="#removeMod" class="btn btn-danger">Remove</button>
                                    <!-- url from api/index.php -->
                                    <button onclick="window.location = '<?php echo !empty($mod['url']) ? $mod['url'] : $SERVER_PROTOCOL.$config['host'].$config['dir'].$mod['type']."s/".$mod['filename'] ?>'" class="btn btn-secondary"><em class="fas fa-file-download"></em> .zip</button>
                                    <?php if (!empty($mod['filename'])) { ?><button onclick="window.location = './functions/mod_extract.php?id=<?php echo $mod['id']; ?>'" class="btn btn-secondary"><em class="fas fa-file-download"></em> .jar</button><?php } ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
                <div class="modal fade" id="removeMod" tabindex="-1" role="dialog" aria-labelledby="rm" aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="rm">Delete file <span id="mod-name-title"></span>?</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="modal-body">
                        Are you sure you want to delete file <span id="mod-name"></span>?
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-dismiss="modal">No</button>
                        <button id="remove-button-force" type="button" class="btn btn-danger" data-dismiss="modal">Force Delete</button>
                        <button id="remove-button" type="button" class="btn btn-danger" data-dismiss="modal">Delete</button>
                      </div>
                    </div>
                  </div>
                </div>
                <h2>Details</h2><hr>
                <form method="POST" action="./functions/edit-mod.php?id=<?php echo $_GET['id'] ?>">
                    <input id="pn" required class="form-control" type="text" name="pretty_name" placeholder="Mod name" value="<?php echo $modpn ?>" />
                    <br />
                    <input id="slug" required pattern="^[a-z0-9\-\_]+$" class="form-control" type="text" name="name" placeholder="Mod slug" value="<?php echo $_GET['id'] ?>" /><br />
                    <textarea class="form-control" type="text" name="description" placeholder="Mod description"><?php echo $modd ?></textarea><br />
                    <input type="submit" name="submit" value="Save" class="btn btn-success">
                    <input type="submit" name="submit" value="Save and close" class="btn btn-success">
                </form>
            </div>
            <script src="./resources/js/page_mod.js"></script>
        </div>
        <?php
        } elseif (uri("/modv")) {
        ?>
        <div class="main">
            <?php
            $mres = $db->query("SELECT * FROM `mods` WHERE `id` = ".$db->sanitize($_GET['id']));
            if ($mres) {
                assert(sizeof($mres)==1);
                $mod = $mres[0];
            }
            ?>
            <script>document.title = 'Solder.cf - Mod - <?php echo addslashes($mod['pretty_name']) ?> - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <div class="card">
                <button onclick="window.location = './mod?id=<?php echo $mod['name'] ?>'" style="width: fit-content;" class="btn btn-primary">
                    <em class="fas fa-arrow-left"></em> Back
                </button><br />
                <h3>Edit <?php echo $mod['pretty_name']." ".$mod['version']; ?></h3>
                <form method="POST" action="./functions/edit-modv.php?id=<?php echo $_GET['id'] ?>">
                        <input required class="form-control" type="text" name="version" placeholder="Mod Version" value="<?php echo $mod['version'] ?>"><br />
                        <div class="input-group">
                            <input class="form-control" type="text" name="author" id="author-input" placeholder="Mod Author" value="<?php echo $mod['author'] ?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button" id="author-save" onclick="authorsave()">Set for all versions</button>
                            </div>
                        </div><br />
                        <div class="input-group">
                            <input class="form-control" type="url" name="link" id="link-input" placeholder="Mod Website" value="<?php echo $mod['link'] ?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button" id="link-save" onclick="linksave()">Set for all versions</button>
                            </div>
                        </div><br />
                        <div class="input-group">
                            <input class="form-control" type="url" name="donlink"  id="donlink-input" placeholder="Author's Website" value="<?php echo $mod['donlink'] ?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button" id="donlink-save" onclick="donlinksave()">Set for all versions</button>
                            </div>
                        </div><br />
                        <input class="form-control" type="url" name="url" <?php if (!empty($mod['url'])) { echo 'placeholder="File URL" value="'.$mod['url'].'" required'; } else { echo 'disabled placeholder="File URL (This is a local mod)"'; } ?>><br />
                        <input required class="form-control" type="text" name="md5" placeholder="File md5 Hash" value="<?php echo $mod['md5'] ?>"><br />
                        <input required class="form-control" required type="text" name="mcversion" placeholder="Minecraft Version" value="<?php echo $mod['mcversion'] ?>"><br />
                        <input required class="form-control" required type="text" name="loadertype" placeholder="forge/fabric/etc." value="<?php echo $mod['loadertype'] ?>"><br />
                        <input type="submit" name="submit" value="Save" class="btn btn-success">
                        <input type="submit" name="submit" value="Save and close" class="btn btn-success">
                </form>
            </div>
            <script src="./resources/js/page_modv.js"></script>
        </div>
        <?php
        } elseif (uri("/about")) {
            ?>
            <script>document.title = 'About - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <div class="main">
                <div class="card">
                    <center>
                        <h1 class="display-4">About Solder<span class="text-muted">.cf</span></h1>
                    </center>
                    <hr>
                    <blockquote class="blockquote">
                        TechnicSolder is an API that sits between a modpack repository and the TechnicLauncher. It
                        allows you to easily manage multiple modpacks in one single location.
                        <br><br>
                        Using Solder also means your packs will download each mod individually. This means the launcher
                        can check MD5's against each version of a mod and if it hasn't changed, use the cached version
                        of the mod instead. What does this mean? Small incremental updates to your modpack doesn't mean
                        redownloading the whole thing every time!
                        <br><br>
                        Solder also interfaces with the Technic Platform using an API key you can generate through your
                        account. When Solder has this key it can directly interact with your Platform account. When
                        creating new modpacks you will be able to import any packs you have registered in your Solder
                        install. It will also create detailed mod lists on your Platform page!
                        <footer class="blockquote-footer">
                            <a href="https://github.com/TechnicPack/TechnicSolder">Technic</a>
                        </footer>
                    </blockquote>
                    <hr>
                    <p>
                        TechnicSolder was originaly developed by <a href="https://github.com/TechnicPack">Technic</a>
                        using the Laravel Framework. However, the application is difficult to install and use.
                        <a href="https://github.com/TheGameSpider/TechnicSolder">Technic Solder - Solder.cf</a> by
                        <a href="https://github.com/TheGameSpider">TheGameSpider</a> runs on pure PHP with zip and MySQL
                        extensions and it's very easy to use. To install, you just need to install zip and MySQL and
                        extract Solder to your root folder. And the usage is even easier! Just Drag n' Drop your mods.
                    </p>
                    <p>Read the licence before redistributing.</p>
                    <div class="card text-white bg-info mb3" style="padding:0">
                        <div class="card-header">License</div>
                        <div class="card-body">
                            <p class="card-text"><?php print(file_get_contents("LICENSE")); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                $(document).ready(function(){
                    $("#nav-settings").trigger('click');
                });
            </script>
            <?php
        }
        elseif (uri("/update")) {
            $version = json_decode(file_get_contents("./api/version.json"), true);
            if ($version['stream']=="Dev"||(isset($config['dev_builds']) && $config['dev_builds']=="on")) {
                if ($newversion = json_decode(file_get_contents("https://raw.githubusercontent.com/TheGameSpider/TechnicSolder/Dev/api/version.json"), true)) {
                    $checked = true;
                } else {
                    $checked = false;
                    $newversion = $version;
                }
            } else {
                if ($newversion = json_decode(file_get_contents("https://raw.githubusercontent.com/TheGameSpider/TechnicSolder/master/api/version.json"), true)) {
                        $checked = true;
                } else {
                    $newversion = $version;
                    $checked = false;
                }
            }
        ?>
        <script>document.title = 'Update Checker - <?php echo $version['version'] ?> - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <div class="main">
                <div class="card">
                    <h2>Solder Updater</h2>
                    <br />
                    <div class="alert <?php 
                        if (version_compare($version['version'], $newversion['version'], '>=') && $checked) { 
                            echo "alert-success";
                        } else { 
                            if ($checked) {
                                echo "alert-info";
                            } else {
                                echo "alert-warning";
                            } 
                        } 
                        ?>" role="alert">
                        <h4 class="alert-heading"><?php 
                            if ($checked) {
                                if (version_compare($version['version'], $newversion['version'], '>=')) {
                                    echo "No updates. Currently on ".$version['version'];
                                } else { 
                                    echo "New update available - ".$newversion['version'].". Curently on ".$version['version']; 
                                }
                            } else {
                                echo "Cannot check for updates!";
                            } 
                        ?></h4>
                        <hr>
                        <p class="mb-0"><?php 
                            if (version_compare($version['version'], $newversion['version'], '>=')) { 
                                echo $version['changelog']; 
                            } else { 
                                echo $newversion['changelog']; 
                            } 
                        ?></p>
                    </div>

                    <?php if (version_compare($version['version'], $newversion['version'], '<>')) { ?>
                        <div class="card text-white bg-info mb3" style="padding: 0px">
                            <div class="card-header">How to update?</div>
                            <div class="card-body">
                                <p class="card-text">
                                    1. Open SSH client and connect to <?php echo $_SERVER['HTTP_HOST'] ?>. <br />
                                    2. login with your credentials <br />
                                    3. write: <br />
                                    <em>cd <?php echo dirname(dirname(get_included_files()[0])); ?> </em><br />
                                    <em>git clone
                                        <?php if ($newversion['stream']=="Dev"||(isset($config['dev_builds']) && ['dev_builds']=="on")) {
                                            echo "--single-branch --branch Dev";
                                        } ?>
                                        https://github.com/TheGameSpider/TechnicSolder.git SolderUpdate </em> <br />
                                    <em>cp -a SolderUpdate/. TechnicSolder/</em> <br>
                                    <em>rm -rf SolderUpdate</em> <br>
                                    <em>chown -R www-data TechnicSolder</em>
                                </p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <script>
                $(document).ready(function(){
                    $("#nav-settings").trigger('click');
                });
            </script>
        <?php
        } elseif (uri("/account")) {
            ?>
            <div class="main">
                <div class="card">
                    <h1>My Account</h1>
                    <hr />
                    <h2>Your Permissions</h2>
                    <input type="text" class="form-control" id="perms" value="<?php echo $_SESSION['perms'] ?>" readonly>
                    <br />
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="perm1" disabled>
                        <label class="custom-control-label" for="perm1">Create, delete and edit modpacks</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="perm2" disabled>
                        <label class="custom-control-label" for="perm2">Create, delete and edit builds</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="perm3" disabled>
                        <label class="custom-control-label" for="perm3">Set public/recommended build</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="perm4" disabled>
                        <label class="custom-control-label" for="perm4">Upload mods and files</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="perm5" disabled>
                        <label class="custom-control-label" for="perm5">Edit mods and files</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="perm6" disabled>
                        <label class="custom-control-label" for="perm6">Download and remove forge versions.</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="perm7" disabled>
                        <label class="custom-control-label" for="perm7">Manage Clients</label>
                    </div>
                    <hr />
                    <h2>User Picture</h2>
                    <img class="img-thumbnail" style="width: 64px;height: 64px" src="data:image/png;base64,<?php
                    $iconq = $db->query("SELECT `icon` FROM `users` WHERE `name` = '".$_SESSION['user']."'");
                    if ($iconq && sizeof($iconq)>=1 && isset($iconq[0]['icon'])) {
                        echo $iconq[0]['icon'];
                    } else {
                        error_log("failed to get icon for name='".$_SESSION['user']."'");
                    }
                     ?>">
                     <br/>
                     <h3>Change Icon</h3>
                     <form enctype="multipart/form-data">
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="newIcon" required>
                            <label class="custom-file-label" for="newIcon">Choose file... (max 15MB)</label>
                        </div>
                     </form>
                     <hr />
                     <h2>Change Password</h2>
                     <form method="POST" action="./functions/chpw.php">
                        <input id="pass1" placeholder="Password" class="form-control" type="password" name="pass"><br />
                        <input id="pass2" placeholder="Confirm Password" class="form-control" type="password"><br />
                        <input class="btn btn-success" type="submit" name="save" id="save-button" value="Save" disabled>
                     </form>
                </div>
                <div class="card">
                    <h2>Technic Solder integration</h2>
                    <?php if (!empty($config['api_key'])) { ?>
                    <font class="text-danger">A server-wide API key has been set.</font>
                    As such, you cannot set your own API key. Contact your server administrator if you think this is a mistake.
                    <?php } else { ?>
                    <p>To integrate with the Technic API, you will need your API key at <a href="https://technicpack.net/" target="_blank">technicpack.net</a>.</p>
                    <p>Sign in (or register), Click on "Edit [My] Profile" in the top right account menu, Click "Solder Configuration", and copy the API key and paste it in the text box below.</p>
                    <form>
                        <input id="api_key" class="form-control" type="text" autocomplete="off" placeholder="Technic Solder API Key" <?php if (get_setting('api_key')) echo 'value="'.get_setting('api_key').'"' ?> <?php if (!empty($config['api_key'])) echo "disabled" ?>/>
                        <br/>
                        <input class="btn btn-success" type="button" id="save_api_key" value="Save" disabled />
                    </form>
                    <br/>
                    <p>Then, copy <?php echo $SERVER_PROTOCOL.$config['host'].$config['dir'].'api' ?> into "Solder URL" text box, and click "Link Solder".</p>
                    <?php } ?>
                    <?php if ($_SESSION['privileged']) { ?>
                    <hr/>
                    <b>You, an administrator, can update the server-wide API key in <a href="admin#solder">Server Settings</a></b>
                    <?php } ?>
                </div>
            </div>
            <script>
                document.title = 'My Account - <?php echo addslashes($_SESSION['name']) ?>';
            </script>
            <script src="./resources/js/page_account.js"></script>
            <?php
        } elseif (uri("/admin")) {
            if (isset($_POST['bug-submit'])) {
                if (isset($_POST['dev_builds'])) {
                    $config['dev_builds'] = "on";
                } else {
                    $config['dev_builds'] = "off";
                }
                if (isset($_POST['use_verifier'])) {
                    $config['use_verifier'] = "on";
                } else {
                    $config['use_verifier'] = "off";
                }
                if (isset($_POST['modrinth_integration'])) {
                    $config['modrinth_integration'] = "on";
                } else {
                    $config['modrinth_integration'] = "off";
                }
                file_put_contents('./functions/config.php', '<?php return '.var_export($config, true).'; ?>');
            } else {
                error_log('no post data');
            }

            ?>
            <div class="main">
                <div class="card">
                    <h1>Administration</h1>
                    <hr />
                    <button class="btn btn-success" data-toggle="modal" data-target="#newUser">New User</button><br />
                    <h2>Users</h2>
                    <div id="info">

                    </div>
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th style="width:35%" scope="col">Name</th>
                            <th style="width:35%" scope="col">Email</th>
                            <th style="width:30%" scope="col"></th>
                        </tr>
                    </thead>
                    <tbody id="users">
                        <?php
                        $users = $db->query("SELECT * FROM `users`");
                        foreach ($users as $user) {
                            if ($user['name']===$_SESSION['user']) {
                                // if editing remember to change in page_admin.js
                                ?>
                                <tr>
                                    <td><?php echo $user['display_name'] ?></td>
                                    <td><?php echo $user['name'] ?></td>
                                    <td>(you)</td>
                                </tr>
                            <?php
                            } else {
                                ?>
                                <tr id="user-<?php echo $user['id'] ?>">
                                    <td scope="row"><?php echo $user['display_name'] ?></td>
                                    <td><?php echo $user['name'] ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                            <font style="display:hidden" id="user-perms-<?php echo $user['id'] ?>" perms="<?php echo $user['perms'] ?>"></font>
                                            <button id="user-edit-<?php echo $user['id'] ?>" onclick="edit(<?php echo $user['id'] ?>,'<?php echo $user['name'] ?>','<?php echo $user['display_name'] ?>')" class="btn btn-primary" data-toggle="modal" data-target="#editUser" >Edit</button>
                                            <button onclick="remove_box(<?php echo $user['id'] ?>,'<?php echo $user['name'] ?>')" data-toggle="modal" data-target="#removeUser" class="btn btn-danger">Remove</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            }
                        }
                        ?>
                    </tbody>
                    </table>
                </div>

                <div class="card">
                    <h1>Server Settings</h1>
                    <hr>
                    <form method="POST">
                        <div class="custom-control custom-switch">
                            <input id="dev_builds" type="checkbox" class="custom-control-input" name="dev_builds" <?php if (isset($config['dev_builds']) && $config['dev_builds']=="on") {echo "checked";} if (json_decode($api_version_json, true)['stream']=="Dev") {echo "checked disabled";} ?> >
                            <label class="custom-control-label" for="dev_builds">Subscribe to dev builds</label>
                        </div>
                        <div class="custom-control custom-switch">
                            <input id="use_verifier" type="checkbox" class="custom-control-input" name="use_verifier" <?php if (isset($config['use_verifier']) && $config['use_verifier']=="on") {echo "checked";} ?> >
                            <label class="custom-control-label" for="use_verifier">
                                Enable Solder Verifier - uses cookies
                            </label>
                            <input type="hidden" name="bug-submit" value="bug-submit">
                        </div>
                        <div class="custom-control custom-switch">
                            <input id="modrinth_integration" type="checkbox" class="custom-control-input" name="modrinth_integration" <?php if (isset($config['modrinth_integration']) && $config['modrinth_integration']=="on") {echo "checked";} ?> >
                            <label class="custom-control-label" for="modrinth_integration">
                                Enable Modrinth integration - uses cookies
                            </label>
                            <input type="hidden" name="bug-submit" value="bug-submit">
                        </div>
                        <br>
                        <input type="submit" class="btn btn-primary" value="Save">
                    </form>
                </div>
                <div class="card">
                    <a name="solder"/>
                    <h2>Server-wide Technic Solder integration</h2>
                    <p>To integrate with the Technic API, you will need your API key at <a href="https://technicpack.net/" target="_blank">technicpack.net</a>.</p>
                    <p>Sign in (or register), Click on "Edit [My] Profile" in the top right account menu, Click "Solder Configuration", and copy the API key and paste it in the text box below.</p>
                    <form>
                        <input id="api_key" class="form-control" type="text" autocomplete="off" placeholder="Technic Solder API Key" <?php if (!empty($config['api_key'])) echo 'value="'.$config['api_key'].'"' ?>/>
                        <br/>
                        <input class="btn btn-success" type="button" id="save_api_key" value="Save" disabled />
                    </form>
                    <br/>
                    <p>Then, copy <?php echo $SERVER_PROTOCOL.$config['host'].$config['dir'].'api' ?> into "Solder URL" text box, and click "Link Solder".</p>
                    <hr/>
                    <b>Setting an API key here will make it available to all other users, and will prevent them from using their own key.</b>
                </div>

                <div class="card">
                    <a href="./configure.php?reconfig&ret=/admin">Reconfigure Server</a>
                </div>
                <div class="modal fade" id="removeUser" tabindex="-1" role="dialog" aria-labelledby="rm"
                     aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="rm">Delete user <span id="user-name-title"></span>?</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="modal-body">
                        Are you sure you want to delete user <span id="user-name"></span>?
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-dismiss="modal">No</button>
                        <button id="remove-button" type="button" class="btn btn-danger" data-dismiss="modal">
                                Delete
                          </button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal fade" id="newUser" tabindex="-1" role="dialog" aria-labelledby="rm"
                     aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="rm">New User</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="modal-body">
                        <form>
                            <input id="email" placeholder="Email" class="form-control" type="email"> <br />
                            <input id="name" placeholder="Username" class="form-control" type="text"><br />
                            <input id="pass1" placeholder="Password" class="form-control" type="password"><br />
                            <input id="pass2" placeholder="Confirm Password" class="form-control" type="password">
                        </form>
                      </div>
                      <div class="modal-footer">
                        <font id="newUser-message"></font>
                        <button type="button" class="btn btn-primary" data-dismiss="modal">Cancel</button>
                        <button id="save-button" type="button" class="btn btn-success" disabled="disabled" onclick='new_user($("#email").val(),$("#name").val(),$("#pass1").val())' >Save</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal fade" id="editUser" tabindex="-1" role="dialog" aria-labelledby="rm"
                     aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="rm">Edit User </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="modal-body">
                        <form>
                            <input type="hidden" id="edit-user-id">
                            <input readonly id="mail2" placeholder="Email" class="form-control" type="text"><br />
                            <input id="name2" placeholder="Username" class="form-control" type="text"><br />
                            <h4>Permissions</h4>
                            <input id="perms" placeholder="Permissions" readonly value="0000000" class="form-control" type="text"><br />

                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="perm1">
                                    <label class="custom-control-label" for="perm1">
                                        Create, delete and edit modpacks
                                    </label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="perm2">
                                <label class="custom-control-label" for="perm2">Create, delete and edit builds</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="perm3">
                                <label class="custom-control-label" for="perm3">Set public/recommended build</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="perm4">
                                <label class="custom-control-label" for="perm4">Upload mods and files</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="perm5">
                                <label class="custom-control-label" for="perm5">Edit mods and files</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="perm6">
                                <label class="custom-control-label" for="perm6">
                                    Download and remove forge versions.
                                </label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="perm7">
                                <label class="custom-control-label" for="perm7">Manage Clients</label>
                            </div>
                        </form>
                      </div>
                      <div class="modal-footer">
                        <font id="editUser-message"></font>
                        <button type="button" class="btn btn-primary" data-dismiss="modal">Cancel</button>
                        <button id="save-button-2" type="button" class="btn btn-success" disabled="disabled" onclick='edit_user($("#mail2").val(),$("#name2").val(),$("#perms").val())'>Save</button>
                      </div>
                    </div>
                  </div>
                </div>
                <script>document.title = 'Admin - <?php echo addslashes($_SESSION['name']) ?>';</script>
                <script src="./resources/js/page_admin.js"></script>
            </div>
        
        <?php } elseif (uri('/clients')) {
        ?>
        <script>document.title = 'Clients - <?php echo addslashes($_SESSION['name']) ?>';</script>
        <div class="main">
            <div class="card">
                <h1>Clients</h1>
                <hr>
                <h3>Add Client</h3>
                <form class="needs-validation" novalidate action="./functions/new-client.php">
                    <div class="form-row">
                        <div class="col">
                            <input type="text" name="name" class="form-control" required placeholder="Name">
                        </div>
                        <div class="col">
                            <input pattern="^[a-f0-9]{8}[-][a-f0-9]{4}[-][a-f0-9]{4}[-][a-f0-9]{4}[-][a-f0-9]{12}$" type="text" name="uuid" class="form-control" required placeholder="UUID">
                            <div class="invalid-feedback">
                                This in not a valid Client ID.
                            </div>
                        </div>

                    </div>
                    <br>
                    <input type="submit" value="Save" class="btn btn-primary">
                </form>
                <table class="table table-striped sortable">
                    <thead>
                        <tr>
                            <td style="width:35%" scope="col" data-defaultsort="AZ">Name</td>
                            <td style="width:50%" scope="col" data-defaultsort="disabled">UUID</td>
                            <td style="width:15%" scope="col" data-defaultsort="disabled"></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $users = $db->query("SELECT * FROM `clients`");
                        foreach ($users as $user) {
                            ?>
                            <tr id="mod-row-<?php echo $user['id'] ?>">
                                <td scope="row"><?php echo $user['name'] ?></td>
                                <td><?php echo $user['UUID'] ?></td>
                                <td><button onclick="remove_box(<?php echo $user['id'] ?>,'<?php echo $user['name'] ?>')" data-toggle="modal" data-target="#removeMod" class="btn btn-danger">Remove</button>
                                    </div></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
                <div class="modal fade" id="removeMod" tabindex="-1" role="dialog" aria-labelledby="rm"
                     aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="rm">Delete client <span id="mod-name-title"></span>?</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="modal-body">
                        Are you sure you want to delete client <span id="mod-name"></span>?
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-dismiss="modal">No</button>
                        <button id="remove-button" type="button" class="btn btn-danger" data-dismiss="modal">
                            Delete
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
            </div>
            <script src="./resources/js/page_clients.js"></script>
        </div>
        <?php } else {
            ?>
        <script>document.title = '404 - Not Found';</script>
        <div style="margin-top: 15%" class="main">

                <center><h1>Error 404 :(</h1></center>
                <center><h2>There is nothing...</h2></center>
        </div>
        <?php }
    }
    ?>
    </body>
</html>
