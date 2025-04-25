<?php
session_start();

define('SUPPORTED_JAVA_VERSIONS', [21,20,19,18,17,16,15,14,13,12,11,1.8,1.7,1.6]);
define('SOLDER_BUILD', '999');
define('METRICS_CACHE_TIME', 3600);
require_once('./functions/configuration.php');
// global $config;
// if (empty($config)) {
    $config=new Config();
// }

// regardless of configuration.php existing, configured=>false forces a re-configure.
if (!$config->exists('configured') || $config->get('configured')!==true) {
    header("Location: ".($config->exists('dir') ? $config->get('dir'):'/')."configure.php");
    exit();
}

require_once("./functions/db.php");
$db = new Db;
if ($db->connect()===FALSE) {
    die("Couldn't connect to database!");
}

$query_num_users = $db->query("SELECT 1 FROM users LIMIT 1");
if (!$query_num_users) {
    die("No users! Please set <font style='font-family:\"Courier New\"'>\"configured\"=>false</font> in <font style='font-family:\"Courier New\"'>config/config.json</font>, and then visit <a href=/configure>/configure</a> to set up a new user.");
}

$url = $_SERVER['REQUEST_URI'];
if ($config->exists('protocol') && !empty($config->get('protocol'))) {
    $protocol = strtolower($config->get('protocol')).'://';
} else {
    $protocol = strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL']))).'://';
}

require('functions/interval_range_utils.php');
require('functions/format_number.php');

require('functions/mp_latest_recommended.php');

function uri($uri) : bool {
    global $url;
    $length = strlen($uri);
    if ($length == 0) {
        return true;
    }
    return (substr($url, -$length) === $uri);
}
function is_https() : bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        return true;
    }
    return false;
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
    header("Location: ".$config->get('dir')."login");
    exit();
}

$user=[];
$modslist=[];

// todo: send hash, not plaintext!
// currently, we're sending a plaintext password, and have no guarantee of https!
if (!empty($_POST['email']) && !empty($_POST['password'])) {
    // loose regex email check
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        die("Malformed email");
    }
    $userq = $db->query("SELECT * FROM users WHERE name = '{$db->sanitize($_POST['email'])}' LIMIT 1");
    if ($userq && sizeof($userq)==1) {
        $user = $userq[0];
        if (password_verify($_POST['password'], $user['pass'])) { // should be sanitized
            $_SESSION['user'] = $_POST['email']; // same as $user['name']
            $_SESSION['name'] = $user['display_name'];
            $_SESSION['perms'] = $user['perms'];
            $_SESSION['privileged'] = ($user['privileged']=='1') ? TRUE : FALSE;
        } else {
            header("Location: ".$config->get('dir')."login?ic");
            exit();
        }
    } else {
        header("Location: ".$config->get('dir')."login?ic");
        exit();
    }       
}

if (isset($_SESSION['user']) && (uri("/login")||uri("/"))) {
    header("Location: ".$config->get('dir')."dashboard");
    exit();
}
if (!isset($_SESSION['user'])&&!uri("/login")) {
    header("Location: ".$config->get('dir')."login");
    exit();
}

require("functions/user-settings.php");

// set user-settings in session from db
if (isset($_SESSION['user'])) {
    if (!got_settings()) {
        read_settings($_SESSION['user']); // puts them in $_SESSION['user-settings']
    }
}

require('functions/solder-updater.php');

if (uri('/update')) {
    header("Access-Control-Allow-Origin: raw.githubusercontent.com");
}

if (!uri("/login")) {
    require('./functions/permissions.php');
    $perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <link rel="icon" href="./resources/wrenchIcon.png" type="image/png" />
        <title>Technic Solder</title>
        <link rel="stylesheet" href="./resources/bootstrap/bootstrap.min.css">
        <link rel="stylesheet" href="./resources/bootstrap/dark/bootstrap.min.css" media="(prefers-color-scheme: dark)">
        <script src="./resources/js/jquery.min.js"></script>
        <script src="./resources/js/popper.min.js"></script>
        <script src="./resources/js/fontawesome.js"></script>
        <script src="./resources/js/global.js"></script>
        <script src="./resources/js/marked.min.js"></script>
        <script src="./resources/js/spark-md5.min.js"></script>
        <script src="./resources/bootstrap/bootstrap.min.js"></script>
        <script src="./resources/bootstrap/bootstrap-sortable.js"></script>
        <link rel="stylesheet" href="./resources/bootstrap/bootstrap-sortable.css" type="text/css">
        <link rel="stylesheet" href="./resources/css/global.css" type="text/css">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body>
        <?php
        // prompt to upgrade
        // you'll want to check config_version value to determine what to do.
        if (!$config->exists('config_version')) { ?>
        <div class="container">
            <div class="alert alert-danger text-center">
                <h3>Upgrade required.</h3>
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
                    <?php
                    if (!is_https()) { ?>
                        <div class="alert alert-danger">
                            This page is served over HTTP, which is insecure! Your password may be visble others.
                        </div>
                    <?php } ?>
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
            $api_version_json = json_decode(file_get_contents('./api/version.json'), true);

            // per modpack
            $modpack_metrics = []; 

            // for all modpacks
            $totaldownloads = 0;
            $totalruns = 0;
            $totallikes = 0;

            $modpacksq = $db->query("SELECT * FROM modpacks");
            $modpacks = []; // associative by id

            if ($config->exists('api_key') && $config->get('api_key')) {
                $api_key = $config->get('api_key');
            } elseif (get_setting('api_key')) {
                $api_key = get_setting('api_key');
            } else {
                $api_key = false;
            }

            foreach($modpacksq as $modpack) {
                $modpacks[$modpack['id']] = $modpack;

                if ($api_key && $modpack['public']==1) {
                    $time = time();

                    // clean up old cached data
                    $db->execute("DELETE FROM metrics WHERE time_stamp < {$time}");

                    $cacheq = $db->query("SELECT info FROM metrics WHERE name = '{$db->sanitize($modpack['name'])}' AND time_stamp >= {$time}");

                    if ($cacheq && !empty($cacheq[0]) && !empty($cacheq[0]['info'])) {
                        $info = json_decode(base64_decode($cacheq[0]['info']), true);
                    } elseif (@$info = json_decode(file_get_contents("http://api.technicpack.net/modpack/{$modpack['name']}?build=".SOLDER_BUILD), true)) {
                        $time = $time + METRICS_CACHE_TIME;
                        $info_data = base64_encode(json_encode($info));

                        if ($config->exists('db-type') && $config->get('db-type')=='sqlite') {
                            $db->execute("
                                INSERT OR REPLACE INTO metrics (
                                    name,
                                    time_stamp,
                                    info
                                )
                                VALUES (
                                    '{$db->sanitize($modpack['name'])}',
                                    {$time},
                                    '{$info_data}'
                            )");
                        } else {
                            $db->execute("
                                REPLACE INTO metrics (
                                    name,
                                    time_stamp,
                                    info
                                )
                                VALUES (
                                    '{$db->sanitize($modpack['name'])}',
                                    {$time},
                                    '{$info_data}'
                            )");
                        }
                    }
                    
                    $modpack_metrics[$modpack['name']] = $info;

                    if (isset($info['error'])) {
                        error_log("/modpack: got error from technicpack api, message='{$info['error']}'");
                    } else {
                        // can be non-numeric values
                        $totaldownloads += empty($info['installs']) ? 0 : $info['installs'];
                        $totalruns += empty($info['runs']) ? 0 : $info['runs'];
                        $totallikes += empty($info['ratings']) ? 0 : $info['ratings'];
                    }
                }
            }

            $num_downloads = number_suffix_string($totaldownloads);
            $downloadsbig = $num_downloads['big'];
            $downloadssmall = $num_downloads['small'];

            $num_runs = number_suffix_string($totalruns);
            $runsbig = $num_runs['big'];
            $runssmall = $num_runs['small'];

            $num_likes = number_suffix_string($totallikes);
            $likesbig = $num_likes['big'];
            $likessmall = $num_likes['small'];
        ?>
        <!-- dark theme for navbar and logo is managed by javascript -->
        <nav id="navbar" class="navbar navbar-light bg-white sticky-top">
            <span class="navbar-brand"  href="#"><img id="techniclogo" alt="Technic logo" class="d-inline-block align-top" height="46px" src="./resources/wrenchIcon.svg"><em id="menuopen" class="fas fa-bars menu-bars"></em> Technic Solder <span id="solderinfo"><?php echo $api_version_json['version']; ?></span></span></span>
            <span style="cursor: pointer;" class="dropdown-toggle" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img id="user-photo" class="img-thumbnail" style="width: 40px;height: 40px" src="functions/get-user-icon.php">
                <span id="user-name" class="navbar-text"><?php echo $_SESSION['name'] ?> </span>
                <div style="left: unset;right: 2px;" class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                    <a class="dropdown-item" href="?logout=true&logout=true" onclick="window.location = window.location+'?logout=true&logout=true'">Log Out</a>
                    <a class="dropdown-item" href="./account" onclick="window.location = './account'">Account</a>
                </div>
            </span>
        </nav>
        <div id="sidenav" class="text-white sidenav">
            <ul class="nav nav-tabs" style="height:100%">
                <li class="nav-item">
                    <a class="nav-link" href="./dashboard"><em class="fas fa-tachometer-alt fa-lg"></em></a>
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
            </ul>
            <div class="tab-content">
                <div class="tab-pane active" id="modpacks" role="tabpanel">
                    <div class="overflow-auto" style="max-height: 88vh">
                        <p class="text-muted">MODPACKS</p>
                        <?php 
                        foreach($modpacksq as $modpack) {
                            if (empty($modpack['name'])) {
                                continue;
                            }
                            ?>
                        <a href="./modpack?id=<?php echo $modpack['id'] ?>">
                            <div class="modpack">
                                <p class="text-white"><img alt="<?php echo $modpack['display_name'] ?>" class="d-inline-block align-top" height="25px" src="<?php echo $modpack['icon']; ?>"> <font id="modpacklist-<?php echo $modpack['id'] ?>"><?php echo $modpack['display_name'] ?></font></p>
                            </div>
                        </a>
                        <?php } 
                        if ($perms->modpack_create()) { ?>
                        <a href="./functions/new-modpack.php"><div class="modpack">
                            <p><em style="height:25px" class="d-inline-block align-top fas fa-plus-circle"></em> Add Modpack</p>
                        </div></a>
                    <?php } ?>
                    </div>
                </div>
                <div class="tab-pane" id="mods" role="tabpanel">
                    <p class="text-muted">LIBRARIES</p>
                    <a href="./lib-mods"><div class="modpack">
                        <p><em class="fas fa-cubes fa-lg"></em> <span style="margin-left:inherit;">Mods</span></p>
                    </div></a>
                    <a href="./modloaders"><div class="modpack">
                        <p><em class="fas fa-database fa-lg"></em> <span style="margin-left:inherit;">Mod loaders</span> </p>
                    </div></a>
                    <a href="./lib-others"><div class="modpack">
                        <p><em class="far fa-file-archive fa-lg"></em> <span style="margin-left:inherit;">Other files</span></p>
                    </div></a>
                </div>
                <div class="tab-pane" id="settings" role="tabpanel">
                    <div style="overflow:auto;max-height: 88vh">
                        <p class="text-muted">SETTINGS</p>
                    <?php if ($perms->privileged()) { ?>
                        <a href="./admin"><div class="modpack">
                            <p><em class="fas fa-user-tie fa-lg"></em> <span style="margin-left:inherit;">Admin</span></p>
                        </div></a>
                    <?php } ?>
                        <a href="./account"><div class="modpack">
                            <p><em class="fas fa-cog fa-lg"></em> <span style="margin-left:inherit;">Account</span></p>
                        </div></a>
                    <?php if ($perms->clients_add() || $perms->clients_delete()) { ?>
                        <a href="./clients"><div class="modpack">
                            <p><em class="fas fa-users fa-lg"></em><span style="margin-left:inherit;">Clients</span></p>
                        </div></a>
                        <?php } ?>
                        <a href="./about"><div class="modpack">
                            <p><em class="fas fa-info-circle fa-lg"></em> <span style="margin-left:inherit;">About</span></p>
                        </div></a>
                        <?php if ($perms->privileged()) { ?>
                        <a href="./update"><div class="modpack">
                            <p><em class="fas fa-arrow-alt-circle-up fa-lg"></em> <span style="margin-left:inherit;">Update</span></p>
                        </div></a>
                        <?php } ?>
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
            <?php if ($api_key && sizeof($modpack_metrics)>0) { ?>
                <div style="margin-left: 0;margin-right: 0" class="row">
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
                    <?php if ($perms->modpack_create() && $perms->build_create()) { ?>
                    <button class="btn btn-success" data-toggle="collapse" href="#collapseMp" role="button" aria-expanded="false" aria-controls="collapseMp">Instant Modpack</button>
                    <div class="collapse" id="collapseMp">
                        <form method="POST" action="./functions/instant-modpack.php">
                            <br>
                            <input autocomplete="off" required id="dn" class="form-control" type="text" name="display_name" placeholder="Modpack name" />
                            <br />
                            <input autocomplete="off" required id="slug" pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$" class="form-control" type="text" name="name" placeholder="Modpack slug (same as on technicpack.net)" />
                            <br />
                            <label for="versions">Minecraft version</label>
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
                            <label for="java">Java version</label>
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
                            <input id="modlist" class="form-control" type="hidden" name="modlist" />
                            <input autocomplete="off" id="modliststr" required readonly class="form-control" type="text" name="modliststr" placeholder="Mods to add" />
                            <br />
                            <input type="submit" id="submit" disabled class="btn btn-primary btn-block" value="Create">
                        </form>

                        <?php
                        if ($perms->mods_upload()) { ?>
                        <div id="upload-card" class="card">
                            <h3>Upload mods</h3>
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
                                        <input type="file" name="fiels" accept=".jar" multiple/>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div style="display: none" id="u-mods" class="card">
                            <h3>Mods</h3>
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
                        echo $api_version_json['warns'];
                        ?>
                    </div>
                    <br />
                    <?php if ($config->exists('use_verifier') && $config->get('use_verifier')=="on") { ?>
                    <button class="btn btn-secondary" data-toggle="collapse" href="#collapseVerify" role="button" aria-expanded="false" aria-controls="collapseVerify">Solder Verifier</button>
                    <div class="collapse" id="collapseVerify">
                        <br />
                        <div class="input-group">
                            <input autocomplete="off" class="form-control" type="text" id="link" placeholder="Modpack slug (same as on technicpack.net)" aria-describedby="search" />
                            <div class="input-group-append">
                                <button class="btn btn-primary" onclick="get();" type="button" id="search">Search</button>
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
            $modpack = $modpacks[$db->sanitize($_GET['id'])];
            $packdata = get_modpack_latest_recommended($db, $modpack['id']);

            $clients = $db->query("SELECT * FROM clients");

            $build_latest = ['id'=>$modpack['id'], 'name'=>'null', 'minecraft'=>'null', 'display_name'=>'null', 'clients'=>'null', 'public'=>0];
            $build_recommended = ['id'=>$modpack['id'], 'name'=>'null', 'minecraft'=>'null', 'display_name'=>'null', 'clients'=>'null', 'public'=>0];

            $latest = false;
            $rec = false;

            if (!empty($packdata['latest'])) {
                $buildq = $db->query("SELECT * FROM builds WHERE modpack = {$modpack['id']} AND id = {$packdata['latest']}");
                if (!empty($buildq)) {
                    $latest = true;
                    $build_latest = $buildq[0];
                }
            }

            if (!empty($packdata['recommended'])) {
                $buildq = $db->query("SELECT * FROM builds WHERE modpack = {$modpack['id']} AND id = {$packdata['recommended']}");
                if (!empty($buildq)) {
                    $rec = true;
                    $build_recommended = $buildq[0];
                }
            }
            ?>
            <script>document.title = 'Modpack - <?php echo addslashes($modpack['display_name']) ?> - <?php echo addslashes($_SESSION['name']) ?>';</script>
            
            <ul class="nav justify-content-end info-versions">
                <li class="nav-item">
                    <a class="nav-link" href="./dashboard"><em class="fas fa-arrow-left fa-lg"></em> <?php echo $modpack['display_name'] ?></a>
                </li>
                <li <?php if (!$latest){ echo "style='display:none'"; } ?> id="latest-v-li" class="nav-item">
                    <span class="navbar-text"><em style="color:#2E74B2" class="fas fa-exclamation"></em> Latest: <strong id="latest-name"><?php echo $build_latest['name'] ?></strong></span>
                </li>
                <li <?php if (!$latest){ echo "style='display:none'"; } ?> id="latest-mc-li" class="nav-item">
                    <span class="navbar-text"><?php if (isset($build_latest['minecraft'])){echo "MC: ";} ?><strong id="latest-mc"><?php echo $build_latest['minecraft'] ?></strong></span>
                </li>
                <div style="width:30px"></div>
                <li <?php if (!$rec){ echo "style='display:none'"; } ?> id="rec-v-li" class="nav-item">
                    <span class="navbar-text"><em style="color:#329C4E" class="fas fa-check"></em> Recommended: <strong id="rec-name"><?php echo $build_recommended['name'] ?></strong></span>
                </li>
                <li <?php if (!$rec){ echo "style='display:none'"; } ?> id="rec-mc-li" class="nav-item">
                    <span class="navbar-text"><?php if (isset($build_recommended['minecraft'])){echo "MC: ";} ?><strong id="rec-mc"><?php echo $build_recommended['minecraft'] ?></strong></span>
                </li>
                <div style="width:30px"></div>
            </ul>

            <div class="main">
                <div class="card">
                    <h2 id="modpack-title"><?php echo $modpack['display_name']; ?></h2>
                </div>
            <?php

            if ($api_key && isset($modpack_metrics[$modpack['name']])) {

                $metric = $modpack_metrics[$modpack['name']];

                if (isset($metric['error'])) { ?>
                <div class="card alert-danger">
                    Error getting metrics from Technic API: <?php echo $metric['error'] ?><br/>
                    <?php
                    // for non-known errors (anything but modpack does not exist)
                    if ($metric['error'] != 'Modpack does not exist') {
                        // server-wide key is set
                        if ($config->exists('api_key') && $config->get('api_key')) { ?>
                            Verify the unique ID (slug) is set in modpack details.
                        <?php } 
                        // user api key is not set
                        elseif (!get_setting('api_key')) { ?>
                            Verify your API key is valid and set in <a href="/account">Account Settings</a>.
                        <?php }
                    } ?>
                </div>
                <?php } else {
                    $num_downloads2 = number_suffix_string($metric['installs']);
                    $num_runs2 = number_suffix_string($metric['runs']);
                    $num_likes2 = number_suffix_string($metric['ratings']);
                    ?>
                <div style="margin-left: 0;margin-right: 0" class="row">
                    <div class="col-4">
                        <div class="card bg-info text-white" style="padding: 0">
                            <div class="card-header">Downloads</div>
                            <div class="card-body">
                                <center>
                                    <h1 class="display-2 w-lg"><em class="d-icon fas fa-download"></em><span class="w-text"><?php echo $num_downloads2['big'] ?></span></h1>
                                    <h1 class="display-4 w-sm"><em class="d-icon fas fa-download"></em><?php echo $num_downloads2['small'] ?></h1>
                                </center>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card text-white bg-success" style="padding: 0">
                            <div class="card-header">Runs</div>
                            <div class="card-body">
                                <center>
                                    <h1 class="display-2 w-lg"><em class="fas fa-play d-icon"></em><span class="w-text"><?php echo $num_runs2['big'] ?></span></h1>
                                    <h1 class="display-4 w-sm"><em class="fas fa-play d-icon"></em><?php echo $num_runs2['small'] ?></h1>
                                </center>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card bg-primary text-white" style="padding: 0">
                            <div class="card-header">Likes</div>
                            <div class="card-body">
                                <center>
                                    <h1 class="display-2 w-lg"><em class="fas fa-heart d-icon"></em><span class="w-text"><?php echo $num_likes2['big'] ?></span></h1>
                                    <h1 class="display-4 w-sm"><em class="fas fa-heart d-icon"></em><?php echo $num_likes2['small'] ?></h1>
                                </center>
                            </div>
                        </div>
                    </div>
                </div>
            <?php }
            } 
            ?>

            <?php if ($perms->modpack_edit()) { ?>
                <div class="card">
                    <h3>Details</h3>
                    <hr>
                    <form method="POST" id="modpack-details">
                        <input hidden type="text" id="modpack-details-id" name="id" value="<?php echo $modpack['id'] ?>">
                        <input autocomplete="off" id="dn" class="form-control" type="text" name="display_name" placeholder="Name" value="<?php echo $modpack['display_name'] ?>" required/>
                        <br />
                        <input autocomplete="off" id="slug" pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$" class="form-control" type="text" name="name" placeholder="Unqiue ID (technicpack.net slug)" value="<?php echo $modpack['name'] ?>" required/>
                        <br />
                        <div class="custom-control custom-checkbox">
                            <input <?php if ($modpack['public']==1){echo "checked";} ?> type="checkbox" name="ispublic" class="custom-control-input" id="public">
                            <label class="custom-control-label" for="public">Public</label>
                        </div><br />

                        <?php if ($perms->modpack_publish()) { ?>
                        <div id="card-allowed-clients" <?php if ($modpack['public']==1) { echo 'style="display:none"'; } ?>>
                            <p>Select which clients are allowed to access this non-public modpack.</p>
                            <input hidden id="modpack_id" value="<?php echo $modpack['id'] ?>">
                            <?php if (sizeof($clients) === 0) { ?>
                            <span class="text-danger">There are no clients in the databse. <a href="./clients">You can add them here</a></span>
                            <br />
                            <?php }
                            $clientlist = !empty($modpack['clients']) ? explode(',', $modpack['clients']) : [];
                            foreach ($clients as $client) {
                                $client_checked = in_array($client['id'], $clientlist) ? 'checked' : ''; ?>
                            <div class="custom-control custom-checkbox">
                                <input class="custom-control-input modpackClientId" id="client-<?php echo $client['id'] ?>" type="checkbox" value="<?php echo $client['id'] ?>" <?php echo $client_checked ?>>
                                <label class="custom-control-label" for="client-<?php echo $client['id'] ?>"><?php echo $client['name']." (".$client['UUID'].")" ?></label>
                            </div>
                        <?php } ?>
                            <br/>
                        </div>
                        <?php } ?>

                        <!-- <div class="btn-group" role="group" aria-label="Actions"> -->
                        <button type="submit" name="type" value="rename" class="btn btn-primary" id="modpack-details-save" disabled>Save</button>
                        <?php if ($perms->modpack_delete()) { ?>
                        <button data-toggle="modal" data-target="#removeModpack" type="button" class="btn btn-danger">Delete</button>
                        <?php } ?>
                        <!-- </div> -->
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
                            Are you sure you want to delete modpack <?php echo $modpack['display_name'] ?> and all its builds?<br/>
                            <?php if ($api_key && isset($modpack_metrics[$modpack['name']])) { ?>
                            This does NOT delete it from technicpack.net!
                            <?php } ?>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-dismiss="modal">No</button>
                            <button onclick="window.location='./functions/remove-modpack.php?id=<?php echo $modpack['id'] ?>'" type="button" class="btn btn-danger" data-dismiss="modal">Delete</button>
                          </div>
                        </div>
                      </div>
                    </div>
                </div>
            <?php 
            }

            if ($perms->build_create()) { 
                $sbn = array();
                $allbuildnames = $db->query("SELECT `name` FROM `builds` WHERE `modpack` = {$modpack['id']}");
                foreach($allbuildnames as $bn) {
                    array_push($sbn, $bn['name']);
                }
                $mpab = array();
                $allbuilds = $db->query("SELECT `id`,`name`,`modpack` FROM `builds`");
                foreach($allbuilds as $b) {
                    $display_nameq=$db->query("SELECT `display_name` FROM `modpacks` WHERE `id` = {$b['modpack']}");
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


                $builds = $db->query("SELECT * FROM `builds` WHERE `modpack` = {$modpack['id']} ORDER BY `id` DESC");
                ?>
                <div class="card">
                    <h3>New Build</h3>
                    <hr>
                    <form action="./functions/new-build.php" method="">
                        <input pattern="^[a-zA-Z0-9.-]+$" required id="newbname" autocomplete="off" class="form-control" type="text" name="name" placeholder="Build name (e.g. 1.0) (a-z, A-Z, 0-9, dot and dash)" />
                        <span id="warn_newbname" style="display: none" class="text-danger">Build with this name already exists.</span>
                        <input hidden type="text" name="id" value="<?php echo $modpack['id'] ?>">
                        <br />
                        <div class="btn-group">
                            <button id="create1" type="submit" name="type" value="new" class="btn btn-primary">Create</button>
                        </div>

                    </form><br />
                </div>
                <div class="card">
                    <h3>Copy Build</h3>
                    <hr>
                    <form id="copybuild" method="POST">
                        <input hidden type="text" name="dest_modpack_id" value="<?php echo $modpack['id'] ?>">
                        <select id="mplist" class="form-control" required> <!-- Not passed to API -->
                            <option value=null>Please select a modpack..</option>
                            <?php
                            foreach ($mps as $pack) {
                                echo "<option value='{$pack['id']}'>{$pack['name']}</option>";
                            }
                            ?>
                        </select>
                        <br />
                        <select id="buildlist" class="form-control" name='src_build_id' required> <!-- This is passed to API -->
                        </select>
                        <br />
                        <input id="newname" class="form-control" type="text" name="new_build_name" pattern="^[a-zA-Z0-9.-]+$" placeholder="New Build Name" required>
                        <span id="warn_newname" class="text-danger" hidden>Build with this name already exists.</span>
                        <br />
                        <button id="copybutton" type="submit" class="btn btn-primary">Copy</button> 
                        <button id="copylatestbutton" type="submit" class="btn btn-secondary">Latest</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Builds</h3>
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
                        <?php foreach($builds as $build) { ?>
                            <tr rec="<?php if ($packdata['recommended']===$build['id']){ echo "true"; } else { echo "false"; } ?>" id="b-<?php echo $build['id'] ?>">
                                <td scope="row"><?php echo $build['name'] ?></td>
                                <td class="<?php if (empty($build['minecraft'])) echo 'alert-danger' ?>"><?php echo $build['minecraft'] ?></td>
                                <td class="<?php if (empty($build['minecraft'])) echo 'alert-danger' ?> d-none d-md-table-cell"><?php echo $build['java'] ?></td>
                                <td class="<?php if (empty($build['minecraft'])) echo 'alert-danger' ?>"><?php echo isset($build['mods']) ? count(explode(',', $build['mods'])) : '' ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                    <?php 
                                    if ($perms->build_edit()) { 
                                        if (empty($build['minecraft'])) { ?> 
                                        <button onclick="edit(<?php echo $build['id'] ?>)" class="btn btn-warning">Set details</button>
                                        <?php } else { ?>
                                        <button onclick="edit(<?php echo $build['id'] ?>)" class="btn btn-primary"><em class="fas fa-edit"></em> </button>
                                        <?php } 
                                    } 
                                    if ($perms->build_delete()) { ?>
                                        <button onclick="remove_box(<?php echo $build['id'] ?>,'<?php echo $build['name'] ?>')" data-toggle="modal" data-target="#removeModal" class="btn btn-danger"><em class="fas fa-times"></em> </button> 
                                    </div>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Publish-actions">
                                    <?php 
                                    } 
                                    if (!empty($build['minecraft'])) {
                                        if ($perms->build_publish()) {
                                            // if public is null then MC version and loader hasn't been set yet
                                            if (isset($build['minecraft']) && $build['public']!='1') { ?>
                                            <button bid="<?php echo $build['id'] ?>" id="pub-<?php echo $build['id']?>" class="btn btn-success" onclick="set_public(<?php echo $build['id'] ?>)">Publish</button>
                                            <?php } ?>
                                            <button bid="<?php echo $build['id'] ?>" id="rec-<?php echo $build['id']?>" class="btn btn-success" onclick="set_recommended(<?php echo $build['id'] ?>)" style="display:<?php echo ($packdata['recommended']!=$build['id']&&$build['public']=='1')?'block':'none' ?>">Recommend</button>
                                            <button bid="<?php echo $build['id'] ?>" id="recd-<?php echo $build['id']?>" class="btn btn-success" style="display:<?php echo ($packdata['recommended']==$build['id']&&$build['public']=='1')?'block':'none' ?>" disabled>Recommended</button>
                                        <?php 
                                        }
                                    }
                                    ?>
                                    </div>
                                </td>
                                <td>
                                    <em id="cog-<?php echo $build['id'] ?>" class="fas fa-cog fa-lg fa-spin" style="margin-top: 0.5rem" hidden></em>
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
                    <div class="modal fade" id="responseModal" tabindex="-1" role="dialog" aria-labelledby="responseModalLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="responseModalLabel"></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body" id="responseModalMessage">
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-dismiss="modal">Dismiss</button>
                            <!-- <button id="remove-button" onclick="" type="button" class="btn btn-danger" data-dismiss="modal">Delete</button> -->
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
        } elseif (uri('/build')) {
            $buildq = $db->query("SELECT * FROM `builds` WHERE `id` = ".$db->sanitize($_GET['id']));
            if (!empty($buildq)) {
                $build = $buildq[0];
            } else {
                die("Build does not exist for id {$db->sanitize($_GET['id'])}");
            }

            $modslist = isset($build['mods']) ? explode(',', $build['mods']) : [];

            // remove empty first item..?
            if (empty($modslist[0])){
                unset($modslist[0]);
            }

            // this entire section is terrible,
            // scroll down to 'Items in build'.

            $modslist_names = [];

            foreach ($modslist as $modid) {
                $nameq = $db->query("SELECT name FROM mods WHERE id = ".$modid);
                if ($nameq && !empty($nameq[0]['name'])) {
                    array_push($modslist_names, $nameq[0]['name']);
                }
            }

            $mpack = $db->query("SELECT * FROM `modpacks` WHERE `id` = {$build['modpack']}");
            if (!empty($mpack)) {
                $mpack = $mpack[0];
            } else {
                die("Modpack does not exist for id {$build['modpack']}");
            }

            $clients = $db->query("SELECT * FROM `clients`");
            $othersq = $db->query("SELECT * FROM `mods` WHERE `type` = 'other'");
            ?>
            <script>document.title = '<?php echo addslashes($mpack['display_name'])." ".addslashes($build['name'])  ?> - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <ul class="nav justify-content-end info-versions">
                <li class="nav-item">
                    <a class="nav-link" href="./modpack?id=<?php echo $mpack['id'] ?>"><em class="fas fa-arrow-left fa-lg"></em> <?php echo $mpack['display_name'] ?></a>
                </li>
                <li <?php if ($mpack['latest']!=$build['id']){ echo "style='display:none'"; error_log(json_encode($mpack)); } ?> id="latest-v-li" class="nav-item">
                    <span class="navbar-text"><em style="color:#2E74B2" class="fas fa-exclamation"></em> Latest</span>
                </li>
                <div style="width:30px"></div>
                <li <?php if ($mpack['recommended']!=$build['id']){ echo "style='display:none'"; } ?> id="rec-v-li" class="nav-item">
                    <span class="navbar-text"><em style="color:#329C4E" class="fas fa-check"></em> Recommended</span>
                </li>
                <div style="width:30px"></div>
            </ul>
            <div class="main">
                <?php if ($perms->build_edit()) { ?>
                <div class="card">
                    <h2>Build details - <?php echo $build['name'] ?></h2>
                    <hr>
                    <?php if (empty($build['minecraft'])) { ?>
                        <h3>Details must be set before mods can be added.</h3>
                    <?php } ?>
                    <form method="POST" id="build-details">
                        <input type="hidden" name="id" value="<?php echo $_GET['id'] ?>">
                        <label for="versions">Minecraft version</label>
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
                                <div style='display:block' class='invalid-feedback'>There are no versions available. Please fetch versions in the <a href='./modloaders'>Forge Library</a></div>
                            <?php }
                            // error_log($loadertype);
                            ?>
                            <input type="text" name="forgec" id="forgec" value="none" hidden required>
                        <br />
                        <label for="java">Java version</label>
                        <select name="java" class="form-control">
                                <?php
                                foreach (SUPPORTED_JAVA_VERSIONS as $jv) {
                                    $selected = isset($build['java']) && $build['java']==$jv ? "selected" : "";
                                    echo "<option value=".$jv." ".$selected.">".$jv."</option>";
                                }
                                ?>
                        </select> <br />
                        <label for="memory">Memory (RAM in MB)</label>
                        <input class="form-control" type="number" id="memory" name="memory" value="<?php echo $build['memory'] ?>" min="1024" max="65536" placeholder="2048" step="512">
                        <br />
                        <?php if ($perms->build_publish()) { ?>
                        <div class="custom-control custom-checkbox">
                            <input <?php if ($build['public']==1) echo "checked" ?> type="checkbox" name="ispublic" class="custom-control-input" id="public">
                            <label class="custom-control-label" for="public">Public Build</label>
                        </div><br />
                        <div id="card-allowed-clients" <?php if ($build['public']==1) { echo 'style="display:none"'; } ?>>
                            <p>Select which clients are allowed to access this non-public build.</p>
                            <input hidden id="build_id" value="<?php echo $_GET['id'] ?>">
                            <?php if (sizeof($clients) === 0) { ?>
                            <span class="text-danger">There are no clients in the databse. <a href="./clients">You can add them here</a></span>
                            <br />
                            <?php }
                            $clientlist = !empty($build['clients']) ? explode(',', $build['clients']) : [];
                            foreach ($clients as $client) {
                                $client_checked = in_array($client['id'], $clientlist) ? 'checked' : ''; ?>
                            <div class="custom-control custom-checkbox">
                                <input class="custom-control-input buildClientId" id="client-<?php echo $client['id'] ?>" type="checkbox" value="<?php echo $client['id'] ?>" <?php echo $client_checked ?>>
                                <label class="custom-control-label" for="client-<?php echo $client['id'] ?>"><?php echo $client['name']." (".$client['UUID'].")" ?></label>
                            </div>
                            <?php } ?>
                            <br/>
                        </div>
                        <?php } ?>

                        <div id="wipewarn" class='text-danger' style='display:none'>Build will be wiped.</div>
                        <button type="submit" id="build-details-save" class="btn btn-success" <?php if (!empty($build['minecraft'])) { echo 'disabled'; } else { echo 'custom_reload="true"'; } ?>>Save</button>
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
                <?php 
                }
                if (!empty($modslist)) { // only empty on new build before mcversion is set?>
                    <div class="card">
                        <h3>Items in build</h3>
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
                                $installed_loader="";

                                $i=0;

                                foreach ($build_mod_ids as $build_mod_id) {

                                    $build_mod_name = $modslist_names[$i];
                                    $i++;

                                    // now get the mod details (before we got ALL the mods...)
                                    $modq = $db->query("SELECT * FROM mods WHERE id = '".$build_mod_id."'");
                                    if (!$modq || sizeof($modq)!=1) {
                                        $mod=[];
                                    } else {
                                        $mod = $modq[0];

                                        // first mod is the loader
                                        if ($installed_loader=="") {
                                            $installed_loader=$mod['loadertype'];
                                        }
                                    
                                        array_push($modsluglist, $mod['name']);

                                        // only check game compatibility if it's a mod!
                                        if ($mod['type']=='mod') {
                                            $mcvrange=parse_interval_range($mod['mcversion']);
                                            $min=$mcvrange['min'];
                                            $minInclusivity=$mcvrange['min_inclusivity'];
                                            $max=$mcvrange['max'];
                                            $maxInclusivity=$mcvrange['max_inclusivity'];

                                            $modvq = $db->query("SELECT id,version FROM mods WHERE type = 'mod'");

                                            $userModVersionOK = in_range($mod['mcversion'], $build['minecraft']);
                                        } else {
                                            $userModVersionOK = true;
                                        }
                                    }

                                    ?>
                                <tr <?php if (empty($mod)) { echo 'class="table-danger"'; } elseif (!$userModVersionOK || empty($mod['version'])) { echo 'class="table-warning"'; } ?> id="mod-<?php echo empty($mod) ? 'missing-'.$build_mod_id : $build_mod_id ?>">
                                    <td scope="row"><?php
                                    echo empty($mod)?'MISSING':$mod['pretty_name'];
                                    if (empty($mod)) { ?>
                                        <span id="warn-incompatible-missing-<?php echo $build_mod_id ?>">: Mod does not exist! Please remove from this build.</span>
                                    <?php } elseif(empty($mod['version'])) { ?>
                                        <span id="warn-incompatible-<?php echo $mod['name'] ?>">: Missing version! You must set it in <a href="modv?id=<?php echo $build_mod_id ?>" target="_blank">mod details</a>.</span>
                                    <?php } elseif(empty($mod['mcversion'])) { ?>
                                        <span id="warn-incompatible-<?php echo $mod['name'] ?>">: Missing mcversion! You must set it in <a href="modv?id=<?php echo $build_mod_id ?>" target="_blank">mod details</a>.</span>
                                    <?php } elseif (!$userModVersionOK) { ?>
                                        <span id="warn-incompatible-<?php echo $mod['name'] ?>">: For Minecraft <?php echo $mod['mcversion'] ?>, you have <?php echo $build['minecraft'] ?>. May not be compatible!</span>
                                    <?php }
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
                                            if (!in_range($mod['mcversion'], $build['minecraft'])){
                                                echo 'false';
                                            } else { 
                                                echo 'true'; 
                                            } 
                                                ?>);" name="bmversions" id="bmversions-<?php 
                                            echo $mod['name'] ?>"><?php

                                            // get versions for mod
                                            $modvq = $db->query("SELECT id,version,loadertype FROM mods WHERE name='{$mod['name']}'");

                                            if($modvq && sizeof($modvq)>0) {
                                                foreach ($modvq as $mv) {
                                                    if ($mv['loadertype']==$installed_loader) {
                                                        if ($mv['id'] == $mod['id']) {
                                                            echo "<option selected value='".$mv['id']."'>".$mv['version']."</option>";
                                                        } else {
                                                            echo "<option value='".$mv['id']."'>".$mv['version']."</option>";
                                                        }
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
                                    <td class="text-right"><?php
                                        if ($perms->mods_delete()) {
                                            // allow deleting non-forges and invalids
                                            if (empty($mod) || $mod['type'] !== "forge") { ?>
                                            <button id="remove-mod-<?php echo $mod['id'] ?>" onclick="remove_mod(<?php echo $build_mod_id ?>, '<?php echo $build_mod_name ?>', '<?php echo $mod['pretty_name'] ?>', '<?php echo $mod['version'] ?>', '<?php echo $mod['mcversion'] ?>')" class="btn btn-danger">
                                                <em class="fas fa-times"></em>
                                            </button>
                                            <?php
                                            }
                                        }
                                    ?></td>
                                </tr>
                                <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($perms->build_edit()) { ?>
                    <div class="card">
                        <h3>Available mods <font id='mods-for-version-string'></font></h3>
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
                                    <th scope="col" style="width: 20%" data-defaultsign="_19">Version</th>
                                    <th scope="col" style="width: 15%" data-defaultsign="_19">Minecraft</th>
                                    <th scope="col" style="width: 15%" data-defaultsort="disabled"></th>
                                </tr>
                            </thead>
                            <tbody id="build-available-mods">
                            </tbody>
                        </table>
                        <div id='no-mods-available' class='text-center' style='display:none'>There are no mods available for this version. Upload mods in <a href='./lib-mods'>Mod Library</a>.</div>
                        <div id='no-mods-available-search-results' class='text-center' style='display:none'>No mods match your search.</div>
                    </div>
                    <div class="card">
                        <h3>Available other files</h3>
                        <hr>
                        <input id="search" type="text" placeholder="Search..." class="form-control">
                        <table class="table table-striped sortable">
                            <thead>
                                <tr>
                                    <th scope="col" style="width: 85%" data-defaultsign="AZ">File Name</th>
                                    <th scope="col" style="width: 15%" data-defaultsort="disabled"></th>
                                </tr>
                            </thead>
                            <tbody id="build-available-others">
                            <?php
                            if (!empty($othersq)) {
                                foreach($othersq as $mod) {
                                    if (!in_array($mod['id'], $modslist)) { 
                                        $add_other_string = "add_o({$mod['id']},'{$mod['name']}','{$mod['pretty_name']}','{$mod['version']}','{$mod['mcversion']}')";
                                        ?>
                                        <tr id="other-add-row-<?php echo $mod['name'] ?>">
                                            <td scope="row"><?php echo $mod['pretty_name'] ?></td>
                                            <td class="text-right"><button id="btn-add-o-<?php echo $mod['id'] ?>" onclick="<?php echo $add_other_string ?>" class="btn btn-primary">Add</button></td>
                                        </tr>
                                        <?php
                                    }
                                }
                            } else { ?>
                                <div style='display:block' class='invalid-feedback'>There are no files available. Please upload files in <a href='./lib-others'>Files Library</a></div>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php }
                }
                ?>
                <script>
                    var INSTALLED_MODS = JSON.parse('<?php echo json_encode($modslist, JSON_UNESCAPED_SLASHES) ?>');
                    var INSTALLED_MOD_NAMES = JSON.parse('<?php echo json_encode($modslist_names, JSON_UNESCAPED_SLASHES) ?>');
                    var BUILD_ID = '<?php echo $build['id'] ?>';
                    var MCV = '<?php echo $build['minecraft'] ?>';
                    var TYPE = '<?php echo $build['loadertype'] ?>';
                </script>
                <script src="./resources/js/page_build.js"></script>
            </div>
        <?php
        }
        elseif (uri('/lib-mods')) {

            $mods = $db->query("SELECT * FROM `mods` WHERE `type` = 'mod' ORDER BY `id` DESC");
            
            if (!empty($mods)) {
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
            }
        ?>
        <script>document.title = 'Mod Library - <?php echo addslashes($_SESSION['name']) ?>';</script>
        <div class="main">

            <div class="card">
                <h2>Mods</h2>
                <div>
                    A mod is a file that does things to your game. All mods which rely on Forge, Neoforge, or Fabric are supported.
                    <br/>Here, you can install mods from <a href="https://modrinth.com" target="_blank">modrinth</a>, or mods you've downloaded from somewhere else.
                    <br/>Note you will need to install a mod loader before using a mod.
                </div>
            </div>
            <?php if ($perms->mods_upload()) { ?>

                <?php if ($config->get('modrinth_integration')=='on') { ?>
            <div class="card">
                <h3>Modrinth installer</h3>
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
                    <div class="row justify-content-center align-items-center">
                        <div class="col-auto">
                            <button class="btn btn-primary" id="leftButton">
                                <i class="bi bi-arrow-left"></i> Previous
                            </button>
                        </div>
                        <div class="col-auto">
                            <input type="text" class="form-control text-center" id="pageRangeInput" value="1-10" aria-label="Page Range" pattern="^\d+\-\d+$">
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-primary" id="rightButton">
                                Next <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                <div id='noresults' class='text-center' style='display:none'>No results</div>
            </div>
            <div class="modal fade" id="description" tabindex="-1" role="dialog" aria-labelledby="description-title" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered modal-fullscreen modal-xl" role="document">
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
                    <br/>
                    <div id="installation-deps" style="display:none">
                    <label for="installation-deps">Dependencies (will be installed)</label>
                    <div id="dependencies"></div>
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
                <h3>Upload</h3>
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
                            <input type="file" name="fiels" accept=".jar" multiple/>
                        </div>
                    </form>
                </div>
            </div>

            <div style="display: none" id="u-mods" class="card">
                <h3>New</h3>
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
                <h3>Remote</h3>
                <a href="./remote-mod"><em class="fas fa-plus-circle"></em> Add mods by direct-download URL</a>
            </div>
            <?php } ?>

            <div class="card">
                <h3>Installed</h3>
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
                        if (!empty($mods)) {
                            foreach ($modsi as $mod) { ?>
                                <tr id="mod-row-<?php echo $mod['name'] ?>">
                                    <td scope="row" <?php if (empty($mod['pretty_name'])) echo 'class="table-danger"' ?>><?php echo empty($mod['pretty_name']) ? "<span class='text-danger'>Unknown</span>" : $mod['pretty_name'] ?></td>
                                    <td <?php if (implode(", ", $mod['author'])=="") echo 'class="table-danger"' ?> class="d-none d-md-table-cell"><?php if (implode(", ", $mod['author'])!=="") { echo implode(", ", $mod['author']); } else { echo "<span class='text-danger'>Unknown</span>"; } ?></td>
                                    <td id="mod-row-<?php echo $mod['name'] ?>-num"><?php echo count($mod['versions']); ?></td>
                                    <td>
                                        <?php if ($perms->mods_edit()) { ?>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                            <a href="mod?id=<?php echo $mod['name'] ?>" class="btn btn-primary">Edit</a>
                                        <?php } if ($perms->mods_delete()) { ?>
                                            <button onclick="remove_box('<?php echo $mod['name'] ?>')" data-toggle="modal" data-target="#removeMod" class="btn btn-danger">Remove</button>
                                        </div>
                                    <?php } ?>
                                    </td>
                                </tr>
                            <?php }
                        } ?>
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
            if (isset($_GET['id'])) {
                $mres = $db->query("SELECT * FROM `mods` WHERE `id` = ".$db->sanitize($_GET['id']));
                if ($mres) {
                    assert(sizeof($mres)==1);
                    $mod = $mres[0];
                }
            } ?>
        <div class="main">
            <script>document.title = 'Add Mod - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <div class="card">
                <button onclick="window.location = './lib-mods'" style="width: fit-content;" class="btn btn-primary"><em class="fas fa-arrow-left"></em> Back</button><br />
                <h2>Add Mod</h2>
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
                    <select class="form-control" name="loadertype">
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
            $buildsq = $db->query("SELECT id,name,mods FROM builds");

            $installed_loader_ids = [];
            if (!empty($buildsq)) {
                foreach ($buildsq as $build) {
                    if (!empty($build['mods'])) {
                        $mods_list = explode(',', $build['mods'],2);
                        // first item is always the mod loader
                        array_push($installed_loader_ids, $mods_list[0]);
                    }
                }
            }

            $used_loaders=[];
            $installed_loaders=[];

            $modsq = $db->query("SELECT * FROM `mods` WHERE `type` = 'forge' ORDER BY `id` DESC");

            if ($modsq){
                foreach ($modsq as $mod) {
                    if (in_array($mod['id'],$installed_loader_ids)) {
                        array_push($used_loaders, $mod['loadertype'].'-'.$mod['mcversion'].'-'.$mod['version']);
                    }
                    array_push($installed_loaders, $mod['loadertype'].'-'.$mod['mcversion'].'-'.$mod['version']);
                }
            }
                                
        ?>
        <script>document.title = 'Forge Versions - <?php echo addslashes($_SESSION['name']) ?>';</script>
        <div class="main">

            <div class="card">
                <h2>Mod loaders</h2>
                <div>
                    A mod loader is a mod that loads other mods.
                    <br/>It is almost always required for your modpack, and must be added to a build before using any other mods.
                    <br/>You can install loaders here, or, you can upload your own <a href="https://support.technicpack.net/hc/en-us/articles/17049267195021-How-to-Create-a-Client-Modpack#Installing-a-mod-loader" target="_blank">modpack.jar</a>.
                </div>
            </div>

            <?php if ($perms->modloaders_upload()) {
            if ($config->get('fabric_integration')=='on') { ?>
            <div class="card" id="fabrics">
                <h3>Fabric installer</h3>
                <form>
                    <label for="lod">Loader Version</label>
                    <select id="lod"></select><br>
                    <label for="ver">Game Version</label>
                    <select id="ver"></select><br>
                    <button id="sub-button" type="button" onclick="download_fabric()" class="btn btn-primary" disabled><em class="fas fa-cog fa-lg fa-spin"></em></button>
                </form>
                <span id="installfabricinfo" style="display:none;"></span>
            </div>
            <?php }
            if ($config->get('neoforge_integration')=='on') { ?>
            <div class="card" id="neoforges">
                <h3>Neoforge installer</h3>
                <form>
                    <label for="lod">Version</label>
                    <select id="lod-neoforge" required></select><br>
                    <!-- <label for="ver">Game Version</label> -->
                    <!-- <select id="ver-neoforge" required></select><br> -->
                    <button id="sub-button-neoforge" type="button" onclick="download_neoforge()" class="btn btn-primary" disabled><em class="fas fa-cog fa-lg fa-spin"></em></button>
                </form>
                <span id="installneoforgeinfo" style="display:none;"></span>
            </div>
            <?php }
            if ($config->get('forge_integration')=='on') { ?>
            <div class="card" id="fetched-mods">
                <h3>Forge installer</h3>
                <form>
                    <button id="fetch-forge" onclick="fetch_forges()" class="btn btn-primary">Show Versions</button>
                </form>
                <table id="table-fetched-mods" style="display:none" class="table table-striped sortable">
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
                <span id="installforgeinfo" class="text-danger" style="display:none;"></span>
            </div>
            <?php } ?>
            <div class="card">
                <h3>Upload</h3>
                <form action="./functions/custom_modloader.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <input class="form-control" type="text" name="version" placeholder="Loader version" required="">
                        <br />
                        <input class="form-control" type="text" name="mcversion" placeholder="Minecraft Version" required="">
                        <br />
                        <div class="custom-file">
                            <input id="forge" type="file" class="form-control-file custom-file-input" name="file" accept=".jar" required>
                            <label class="custom-file-label" for="forge">
                                <i id="file-icon" class="fas fa-upload"></i> Choose modpack.jar file...
                            </label>
                        </div>
                        <br /><br>
                        <select class="form-control" name="type">
                            <option value="forge">Forge</option>
                            <option value="neoforge">Neoforge</option>
                            <option value="fabric">Fabric</option>
                        </select>
                        <br/>
                        <button type="submit" class="btn btn-primary">Upload</button>
                </div>
                </form>
            </div>
            <?php } ?>

            <div class="card">
                <h3>Installed</h3>
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
                        if ($modsq){
                            foreach ($modsq as $mod) { 
                                $remove_box_str = "remove_box({$mod['id']}, '{$mod['loadertype']}', '{$mod['mcversion']}', '{$mod['version']}')";
                                ?>
                            <tr id="mod-row-<?php echo $mod['id'] ?>">
                                <td scope="row"><?php echo $mod['mcversion'] ?></td>
                                <td><?php echo $mod['version'] ?></td>
                                <td><?php echo $mod['loadertype']?></td>
                                <td>
                                <?php if ($perms->modloaders_delete()) { ?>
                                    <button  onclick="<?php echo $remove_box_str ?>" data-toggle="modal" data-target="#removeMod" class="btn btn-danger btn-sm">Remove</button>
                                <?php } ?>
                                </td>
                                <td><em style="display: none" class="fas fa-cog fa-spin fa-sm"></em></td>
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
                    <h5 class="modal-title" id="rm">Delete loader <span id="mod-name-title"></span>?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                    Are you sure you want to delete loader <span id="mod-name"></span>? Mod's file will be deleted too.
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">No</button>
                    <button id="remove-button" type="button" class="btn btn-danger" data-dismiss="modal">Delete</button>
                  </div>
                </div>
              </div>
            </div>

            <script>
            <?php
            if (!empty($installed_loaders)) {
                $installed_loaders_json = json_encode($installed_loaders, JSON_UNESCAPED_SLASHES);
                echo "var installed_loaders=JSON.parse('{$installed_loaders_json}');";
            } else {
                echo 'var installed_loaders=[];';
            }
            if (!empty($used_loaders)) {
                $used_loaders_json = json_encode($used_loaders, JSON_UNESCAPED_SLASHES);
                echo "var used_loaders=JSON.parse('{$used_loaders_json}');";
            } else {
                echo 'var used_loaders=[];';
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
            <div class="card">
                <h2>Other files</h2>
                <div>
                    These files will be extracted to modpack's root directory. (e.g. Config files, worlds, resource packs, etc.)
                </div>
            </div>
            <?php if ($perms->files_upload()) { ?>
            <div id="upload-card" class="card">
                <h3>Upload</h3>
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
                            <input type="file" name="fiels" accept=".zip" multiple />
                        </div>
                    </form>
                </div>
            </div>
            <div style="display: none" id="u-mods" class="card">
                <h3>New Files</h3>
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
                <h3>Installed</h3>
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
                                             <?php if ($perms->files_delete()) { ?>
                                            <button onclick="remove_box(<?php echo $mod['id'].",'".$mod['name']."'" ?>)" data-toggle="modal" data-target="#removeMod" class="btn btn-danger">Remove</button>
                                            <?php } ?>
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
                assert(sizeof($mres)==1);
                $file = $mres[0];
            }

            $zip = new ZipArchive;
            $paths = [];
            if ($zip->open("./others/{$file['filename']}")===TRUE) {
                for ($i=0; $i<$zip->numFiles; $i++) {
                    array_push($paths, $zip->getNameIndex($i));
                }
                $zip->close();
            }
            
            $paths_json = json_encode($paths);
            ?>
            <script>document.title = 'File - <?php echo addslashes($file['name']) ?> - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <div class="main">
                <div class="card">
                    <button onclick="window.location = './lib-others'" style="width: fit-content;" class="btn btn-primary">
                        <em class="fas fa-arrow-left"></em> Back
                    </button><br />
                    <h3><?php echo $file['filename'] ?></h3>
                    <hr>
                    <p>MD5: <?php echo $file['md5'] ?><br >
                    Size: <?php echo formatSizeUnits($file['filesize']) ?></p>
                    <h3>Files:</h3>
                    <pre id="files_ul">
                    </pre>
                    <h4 id="loadingfiles"><em class="fas fa-cog fa-spin"></em> Loading...</h4>
                </div>
                <script>
                <?php echo "var paths = JSON.parse('{$paths_json}');"; ?>
                </script>
                <script src="./resources/js/page_file.js"></script>
            </div>
            <?php
        } elseif (uri('/mod')) {
            assert(!empty($_GET['id']));
            
            $mres = $db->query("SELECT * FROM mods WHERE name = '{$db->sanitize($_GET['id'])}'");

            $mod_slug = '';
            $mod_name = '';
            $mod_description = '';
            $mod_author = '';

            // get details from first mod version
            foreach ($mres as $mod) {
                $mod_slug = $mod['name'];
                $mod_name = $mod['pretty_name'];
                $mod_description = $mod['description'];
                $mod_author = $mod['author'];
                if (!empty($mod_slug)) {
                    break;
                }
            }
        ?>
        <div class="main">
            <script>document.title = 'Mod - <?php echo addslashes($_GET['id']) ?> - <?php echo addslashes($_SESSION['name']) ?>';
            </script>
            <button onclick="window.location = './lib-mods'" style="width: fit-content;" class="btn btn-primary">
                <em class="fas fa-arrow-left"></em> Back
            </button><br />
            <div class="card">
                <h2><?php echo $mod_name ?></h2>
            </div>
            <div class="card">
                <h3>Versions</h3>
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
                    <?php foreach ($mres as $mod) { ?>
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
                                    <button onclick="window.location = '<?php echo !empty($mod['url']) ? $mod['url'] : $protocol.$config->get('host').$config->get('dir').$mod['type']."s/".$mod['filename'] ?>'" class="btn btn-secondary"><em class="fas fa-file-download"></em> .zip</button>
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
            </div>
            <div class="card">
                <h3>Details</h3><hr>
                <form method="POST" action="./functions/edit-mod.php">
                    <input id="pn" required class="form-control" type="text" name="pretty_name" placeholder="Mod name" value="<?php echo $mod_name ?>" />
                    <br />
                    <input type="hidden" name="name" value="<?php echo $mod_slug ?>"/>
                    <input id="slug" disabled class="form-control" type="text" placeholder="Mod slug" value="<?php echo $mod_slug ?>" /><br />
                    <input id="author"required class="form-control" type="text" name="author" placeholder="Author" value="<?php echo $mod_author ?>"/><br/>
                    <textarea class="form-control" type="text" name="description" placeholder="Mod description"><?php echo $mod_description ?></textarea><br />
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
        } elseif (uri("/update") && $perms->privileged()) {
            $updater = new Updater(__DIR__,$config);
            $update_status = $updater->check();

            // we set cors at the top of this document if uri is /update
            ?>
            <script src="resources/js/page_update.js"></script>
            <script>
                document.title = `Update Checker - ${local_version()} - <?php echo addslashes($_SESSION['name']) ?>`;
            </script>
            <div class="main">
                <div class="card">
                    <h2>Solder Updater</h2>
                    <br />
                    <div id="updater-container" class="alert <?php 
                        if ($update_status === UP_TO_DATE) { 
                            echo "alert-success";
                        } elseif ($update_status === OUTDATED) {
                            echo "alert-info";
                        } elseif ($update_status === NO_GIT || $update_status === UPDATES_DISABLED) {
                        } else {
                            echo "alert-warning";
                        } 
                        ?>" role="alert">
                        <h4 class="alert-heading"><?php 
                        if ($update_status === UP_TO_DATE) {
                            ?>No updates. Currently on <script>local_version()</script><?php
                        } elseif ($update_status === OUTDATED) {
                            ?>New update available - <script>remote_version()</script> Curently on <script>local_version()</script><?php
                        } elseif ($update_status === NO_GIT || $update_status === UPDATES_DISABLED) {
                            ?>
                            <div id="updater-loading" style="display:none"><em class="fas fa-cog fa-lg fa-spin" style="margin-top: 0.5rem"></em> Checking for updates...</div>
                            <script>check_for_json_updates()</script><?php
                        } else {
                            echo "Cannot check for updates!";
                        } 
                        ?></h4>
                        <hr>
                        <p class="mb-0"><?php 
                            if ($update_status === UP_TO_DATE) { 
                                ?><script>local_changelog()</script><?php
                            } elseif ($update_status === OUTDATED) { 
                                ?>
                                <div id="install-updates-box">
                                    <button id="install-updates" class="btn btn-success">Install update</button>
                                    <p>May cause loss of data! If you have made any changes to project files, please back them up before updating.</p>
                                </div>
                                <div id="install-updates-in-progress" style="display:none"><em class="fas fa-cog fa-lg fa-spin" style="margin-top: 0.5rem"></em> Updating...</div>
                                <br/>
                                <script>remote_changelog()</script><?php 
                            } elseif ($update_status == NO_GIT || $update_status === UPDATES_DISABLED) {
                                ?><script>check_for_json_updates_changelog()</script><?php
                            } else {
                                echo $update_status;
                                $logs = $updater->logs();
                                if (!empty($logs)) { // if we don't get to executing git then we get empty logs
                                    $logs_str_raw = implode("\n",$updater->logs());
                                    $logs_str = preg_replace('/[^\w\s\-\+\:\/\.\;\'\"]/', '', $logs_str_raw);
                                    ?><br/><pre class="code"><?php
                                    echo $logs_str;
                                    ?></pre><?php
                                }
                            }
                        ?></p>
                    </div>

                    <?php if ($update_status===OUTDATED) { ?>
                        <div class="card text-white bg-info mb3" style="padding: 0px">
                            <div class="card-header">How to update?</div>
                            <div class="card-body">
                                <p class="card-text">
                                    1. Open SSH client and connect to <?php echo $_SERVER['HTTP_HOST'] ?> (or your SSH host). <br/>
                                    2. Login with your SSH/account credentials <br/>
                                    3a. If using 1.4.0 or newer:<br />
                                    <div class=code>
                                        cd <?php echo dirname(get_included_files()[0]) ?><br/>
                                        git pull
                                    </div>
                                    3b. If using an older version before 1.4.0: <br />
                                    <div class=code>
                                        cd <?php echo dirname(dirname(get_included_files()[0])) ?><br/>
                                        git clone <?php if (strtolower($api_version_json['stream'])==="dev" || ($config->exists('dev_builds') && ['dev_builds']==="on")) echo "--single-branch --branch Dev" ?>
                                            https://github.com/TheGameSpider/TechnicSolder.git SolderUpdate<br/>
                                        cp -a SolderUpdate/. TechnicSolder/<br/>
                                        rm -rf SolderUpdate<br/>
                                        chown -R www-data TechnicSolder
                                    </div>
                                </p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <script src="resources/js/page_update.js"></script>
            <script>
                $(document).ready(function(){
                    $("#nav-settings").trigger('click');
                });
            </script>
        <?php
        } elseif (uri("/account")) {
            ?>
            <script> document.title = 'My Account - <?php echo addslashes($_SESSION['name']) ?>'; </script>
            <div class="main">
                <div class="card">
                    <h2>Account</h2>
                </div>
                <div class="card">   
                    <h3>Permissions</h3>
                    <input type="text" class="form-control" id="perms" value="<?php echo $_SESSION['perms'] ?>" readonly hidden>
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
                        <label class="custom-control-label" for="perm3">Publish and manage client access for builds/modpacks</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="perm4" disabled>
                        <label class="custom-control-label" for="perm4">Upload mods and files</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="perm5" disabled>
                        <label class="custom-control-label" for="perm5">Edit and delete mods and files</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="perm6" disabled>
                        <label class="custom-control-label" for="perm6">Upload and delete modloaders</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="perm7" disabled>
                        <label class="custom-control-label" for="perm7">Add and delete clients</label>
                    </div>
                </div>
                <div class="card">
                    <h3>User Picture</h3>
                    <img id="user-photo-preview" class="img-thumbnail" style="width:128px; height: 128px" src="functions/get-user-icon.php">
                     <br/>
                     <form enctype="multipart/form-data">
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="newIcon" required>
                            <label class="custom-file-label" for="newIcon">Choose file... (max 4MB)</label>
                        </div>
                     <font id="user-photo-message" style="display: none;"></font>
                     </form>
                     <hr />
                     <h3>Change Name</h3>
                     <form id="change-name">
                        <input id="newname" placeholder="Name" class="form-control" type="text" name="display_name" value="<?php echo $_SESSION['name'] ?>" oldvalue="<?php echo $_SESSION['name'] ?>"><br />
                        <input id="newnamesubbmit" class="btn btn-success" type="submit" name="save" value="Save" disabled>
                     <font id="change-name-message" style="display: none;"></font>
                     </form>
                     <hr />
                     <h3>Change Password</h3>
                     <form id="change-password">
                        <input id="oldpass" placeholder="Current Password" class="form-control" type="password" name="oldpass"><br />
                        <input id="pass1" placeholder="New Password" class="form-control" type="password" name="pass"><br />
                        <input id="pass2" placeholder="Confirm Password" class="form-control" type="password"><br />
                        <input class="btn btn-success" type="submit" name="save" id="save-button" value="Save" disabled>
                     <font id="change-password-message" style="display: none;"></font>
                     </form>
                </div>
                <div class="card">
                    <h3>Technic Solder integration</h3>
                <?php if ($config->exists('api_key') && !empty($config->get('api_key'))) { ?>
                    <font class="text-danger">A server-wide API key has been set.</font>
                    <?php if ($perms->privileged()) { ?>
                    <span>You, an administrator, can update the server-wide API key in <a href="admin#solder">Server Settings</a></span>
                    <?php } else { ?>
                    As such, you cannot set your own API key. Contact your server administrator if you think this is a mistake.
                    <?php } 
                } else { ?>
                    <p>To integrate with the Technic API, you will need your API key from <a href="https://technicpack.net/" target="_blank">technicpack.net</a>; Sign in (or register), "Edit [My] Profile" in the top right account menu, "Solder Configuration", and copy the API key and paste it in the text box below.</p>
                    <form>
                        <input id="api_key" class="form-control" type="text" autocomplete="off" placeholder="Technic Solder API Key" <?php if (get_setting('api_key')) echo 'value="'.get_setting('api_key').'"'; ?>/>
                        <br/>
                        <input class="btn btn-success" type="button" id="save_api_key" value="Save" disabled />
                    </form>
                    <br/>
                    <p>Then, copy <?php echo $protocol.$config->get('host').$config->get('dir').'api' ?> into "Solder URL" text box, and click "Link Solder".</p>
                <?php } ?>
                </div>
            </div>
            <script src="./resources/js/page_account.js"></script>
        <?php
        } elseif (uri("/admin") && $perms->privileged()) {
            $usersq = $db->query("SELECT * FROM `users`");
            ?>
            <script>document.title = 'Admin - <?php echo addslashes($_SESSION['name']) ?>';</script>
            <div class="main">
                <div class="card">
                    <h2>Administration</h2>
                </div>

                <div class="card">
                    <h3>Users</h3>
                    <div id="info">
                    </div>
                    <button class="btn btn-success" data-toggle="modal" data-target="#newUser">New User</button><br />
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th style="width:35%" scope="col">Name</th>
                            <th style="width:35%" scope="col">Email</th>
                            <th style="width:30%" scope="col"></th>
                        </tr>
                    </thead>
                    <tbody id="users">
            <?php foreach ($usersq as $user) {
                // if editing remember to change in page_admin.js 
                if ($user['name']===$_SESSION['user']) { ?>
                        <tr>
                            <td><?php echo $user['display_name'] ?></td>
                            <td><?php echo $user['name'] ?></td>
                            <td>(you)</td>
                        </tr>
            <?php } else { ?>
                        <tr id="user-<?php echo $user['id'] ?>">
                            <td scope="row" id="displayname-<?php echo $user['id'] ?>"><?php echo $user['display_name'] ?></td>
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
                    <h3>Server Settings</h3>
                    <form id="server-settings">
                        <div class="custom-control custom-switch">
                            <input id="dev_builds" type="checkbox" class="custom-control-input" name="dev_builds" <?php if (strtolower($api_version_json['stream'])==="dev") { echo "checked disabled"; } elseif ($config->exists('dev_builds') && $config->get('dev_builds')==="on") { echo "checked"; } ?>>
                            <label class="custom-control-label" for="dev_builds">Subscribe to dev builds</label>
                        </div>
            <?php if (strtolower($api_version_json['stream'])==="dev") { ?>
                        You are on the Dev release channel.
            <?php } else { ?>
                        Switching to the Dev release channel is permanent!
            <?php } ?>
                        <br/>
                        <div class="custom-control custom-switch">
                            <input id="enable_self_updater" type="checkbox" class="custom-control-input" name="enable_self_updater" <?php if ($config->exists('enable_self_updater') && $config->get('enable_self_updater')=="on") {echo "checked";} ?> >
                            <label class="custom-control-label" for="enable_self_updater">
                                Enable Git Self-Updater
                            </label>
                        </div>
                        <div class="custom-control custom-switch">
                            <input id="use_verifier" type="checkbox" class="custom-control-input" name="use_verifier" <?php if ($config->exists('use_verifier') && $config->get('use_verifier')=="on") {echo "checked";} ?> >
                            <label class="custom-control-label" for="use_verifier">
                                Enable Solder Verifier (check status of modpack on <a href="https://technicpack.net" target="_blank">technicpack.net</a>)
                            </label>
                        </div>
                        <br/>
                        Mod installers
                        <div class="custom-control custom-switch">
                            <input id="modrinth_integration" type="checkbox" class="custom-control-input" name="modrinth_integration" <?php if ($config->exists('modrinth_integration') && $config->get('modrinth_integration')=="on") {echo "checked";} ?> >
                            <label class="custom-control-label" for="modrinth_integration">
                                Modrinth.com
                            </label>
                        </div>
                        <br/>
                        Mod loader installers
                        <div class="custom-control custom-switch">
                            <input id="forge_integration" type="checkbox" class="custom-control-input" name="forge_integration" <?php if ($config->exists('forge_integration') && $config->get('forge_integration')=="on") {echo "checked";} ?> >
                            <label class="custom-control-label" for="forge_integration">
                                Forge <a href="https://minecraftforge.net" target="_blank">MinecraftForge.net</a>
                            </label>
                        </div>
                        <div class="custom-control custom-switch">
                            <input id="neoforge_integration" type="checkbox" class="custom-control-input" name="neoforge_integration" <?php if ($config->exists('neoforge_integration') && $config->get('neoforge_integration')=="on") {echo "checked";} ?> >
                            <label class="custom-control-label" for="neoforge_integration">
                                Neoforge <a href="https://neoforged.net" target="_blank">NeoForged.net</a>
                            </label>
                        </div>
                        <div class="custom-control custom-switch">
                            <input id="fabric_integration" type="checkbox" class="custom-control-input" name="fabric_integration" <?php if ($config->exists('fabric_integration') && $config->get('fabric_integration')=="on") {echo "checked";} ?> >
                            <label class="custom-control-label" for="fabric_integration">
                                Fabric <a href="https://fabricmc.net" target="_blank">FabricMC.net</a>
                            </label>
                        </div>
                        <br>
                        <input type="submit" id="save-server-settings" class="btn btn-primary" value="Save" disabled>
                    </form>
                </div>
                <div class="card">
                    <a name="solder"/>
                    <h3>Server-wide Technic Solder integration</h3>
                    <p>To integrate with the Technic API, you will need your API key from <a href="https://technicpack.net/" target="_blank">technicpack.net</a>; Sign in (or register), "Edit [My] Profile" in the top right account menu, "Solder Configuration", and copy the API key and paste it in the text box below.</p>
                    <form>
                        <input id="api_key" class="form-control" type="text" autocomplete="off" placeholder="Technic Solder API Key" <?php if ($config->exists('api_key') && !empty($config->get('api_key'))) echo "value='{$config->get('api_key')}'" ?>/>
                        <br/>
                        <input class="btn btn-success" type="button" id="save_api_key" value="Save" disabled />
                    </form>
                    <br/>
                    <p>Then, copy <?php echo "{$protocol}{$config->get('host')}{$config->get('dir')}api" ?> into "Solder URL" text box, and click "Link Solder".</p>
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
                        <button id="save-button" type="button" class="btn btn-success" disabled="disabled" onclick="new_user($('#email').val(),$('#name').val(),$('#pass1').val())" >Save</button>
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
                            <input id="perms" placeholder="Permissions" readonly value="0000000" class="form-control" type="text" hidden>
                            <p>User will need to log out and back in again for new permissions to take effect.</p>
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
                        <button id="save-button-2" type="button" class="btn btn-success" disabled="disabled" onclick="edit_user($('#mail2').val(),$('#name2').val(),$('#perms').val())">Save</button>
                      </div>
                    </div>
                  </div>
                </div>
                <script src="./resources/js/page_admin.js"></script>
            </div>
        
        <?php } elseif (uri('/clients')) {
            $clientsq = $db->query("SELECT * FROM `clients`"); ?>
        <script>document.title = 'Clients - <?php echo addslashes($_SESSION['name']) ?>';</script>
        <div class="main">
            <div class="card">
                <h2>Clients</h2>
            </div>
            <div class="card">
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
            <?php foreach ($clientsq as $user) { ?>
                            <tr id="mod-row-<?php echo $user['id'] ?>">
                                <td scope="row"><?php echo $user['name'] ?></td>
                                <td><?php echo $user['UUID'] ?></td>
                                <td><button onclick="remove_box(<?php echo $user['id'] ?>,'<?php echo $user['name'] ?>')" data-toggle="modal" data-target="#removeMod" class="btn btn-danger">Remove</button>
                                    </div></td>
                            </tr>
            <?php } ?>
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
        <?php } else { ?>
        <script>document.title = '404 - Not Found';</script>
        <div style="margin-top: 15%" class="main">

                <center><h1>Error 404 :(</h1></center>
                <center><h3>There is nothing...</h3></center>
        </div>
        <?php }
    } ?>

        <script src="./resources/js/body.js"></script>
    </body>
</html>
