<?php
session_start();

require('./constants.php');

define('ICON', "iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAB9ElEQVR4Xu2bSytEcRiHZyJRaDYWRhJilFlYKjakNOWS7OxEGCRGpAg1KykRSlHSKLkO0YyFhSiRIQmbIcVEsnCXW/EJPB/g9Jvt0/8s3t73+b3nnDnmpZWaXxP8dssRm6yL+XTc9OO1Ib+9GWCe60BuyUpEvvDYiNysAqgDNAJygCSoFPi/AoaPwbCvXnRAKKoZc/T7rA/5kasEeV1wEvlJnBf5lM+KfD16mPcAFUAdoBGQA8gSkqBSwOAxmBZ8QQdsOTIwRzsPOae7Iy/w/Op3DvLwZd4zgrYnPJ83Xcp7gAqgDtAIyAFkCUlQKWDwGKzdPeUH//ftmKPz9ePIQ6m1yANufq+QPteK58s6tpHvRZTxHqACqAM0AnIAWkISVAoYOwaf13bQAZn2WSzAQ1EB38/3FyP/9R0jz/K/I/cMxSM3VSTzHqACqAM0AnIAWUISVAoYPAbfe6/RAV07b5ijH/uFyD8Dd8jnejy8R+TwnuG8GsTzpXdJvAeoAOoAjYAcQJaQBJUCBo9B+6sDHfDSUoM5Wm1uQ34Z60YeMzOB3DJygNy5yU+sHGNNvAeoAOoAjYAcQJaQBJUCBo/B7Cr+aMrvnMEctVbx9wCVXbxINboS8Pqu0DnyFDf//2B0o4H3ABVAHaARwD1ADpAElQKGjsE/aSRgFj7BEuwAAAAASUVORK5CYII=");
define('OVERWRITE_USER', true);

require_once('./functions/configuration.php');
// global $config;
// if (empty($config)) {
$config = new Config();
// }

if (!$config->exists('configured')) {
    $config->set('configured', false);
}

if (file_exists('./functions/settings.php')) {
    include("./functions/settings.php");
}
if (isset($_GET['reconfig'])) {
    error_log("configure.php: reconfiguring");
    if (!isset($_SESSION['user'])) {
        die("You need to be logged in!");
    }
    require_once('./functions/permissions.php');
    global $perms;
    $perms = new Permissions($_SESSION['perms'], $_SESSION['privileged']);
    if (!$perms->privileged()) {
        die("Insufficient permission!");
    }
} elseif ($config->exists('configured') && $config->get('configured')) {
    error_log("configure.php: already configured, redirecting to login");
    header("Location: ".$config->get('dir')."login");
    exit();
}

require_once("./functions/db.php");
// global $db;
// if (empty($db)) {
$db = new Db();
// }

$connection_failed = false;

if (isset($_POST['host'])) {
    $api_key = $_POST['api_key'] ?: getenv('SOLDER_API_KEY') ?: '';

    $host = strtolower($_POST['host'] ?: getenv('HOST') ?: $_SERVER['HTTP_HOST']);
    $dir = $_POST['dir'] ?: getenv('DIR') ?: preg_replace('#/configure/?$#', '', $_SERVER['REQUEST_URI']);

    $email = strtolower($_POST['email'] ?: getenv('ADMIN_EMAIL') ?: '');
    $pass = password_hash($_POST['pass'] ?: GETENV('ADMIN_PASSWORD') ?: '', PASSWORD_DEFAULT);
    $name = $_POST['author'] ?: getenv('ADMIN_NAME') ?: '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('Bad input data; email');
    }
    if (!preg_match("/^[a-zA-Z\s\-\.\s]+$/", $name)) {
        die("Bad input data; name");
    }
    if (!ctype_alnum($api_key) || strlen($api_key) != 32) {
        die("Bad input data; api_key");
    }

    // default to whatever works 'sqlite'
    $dbtype = $_POST['db-type'] ?: getenv('DB_TYPE') ?: 'sqlite';
    $dbhost = $_POST['db-host'] ?: getenv('DB_HOST') ?: '';
    $dbname = $_POST['db-name'] ?: getenv('DB_NAME') ?: '';
    $dbuser = $_POST['db-user'] ?: getenv('DB_USER') ?: '';
    $dbpass = $_POST['db-pass'] ?: getenv('DB_PASS') ?: '';

    if ($dbtype === 'sqlite') {
        $dbhost = '';
        $dbuser = '';
        $dbpass = '';
        $dbname = '';
    }

    // default to whatever works 'none'
    $cache         = $_POST['cache']          ?: getenv('CACHE')          ?: 'none';
    $redishost     = $_POST['redis-host']     ?: getenv('REDIS_HOST')     ?: '';
    $redisport     = $_POST['redis-port']     ?: getenv('REDIS_PORT')     ?: '';
    $redispassword = $_POST['redis-password'] ?: getenv('REDIS_PASSWORD') ?: '';

    if ($cache === 'none') {
        $redishost = '';
        $redisport = '';
        $redispassword = '';
    }

    if (!preg_match("/^[a-z0-9\.\-]+$/", $host)) {
        die("Bad input data; host");
    }
    if ($dir !== '/' && (!str_starts_with($dir, '/') || !str_ends_with($dir, '/'))) {
        die("Bad input data; dir must start and end with a '/'.");
    }
    if (!is_dir($dir)) {
        die("Bad input data; dir (path) does not exist. This should be the directory containing the TechnicSolder repository.");
    }

    $version_raw = @file_get_contents("./api/version.json");
    if ($version_raw === false) {
        die("Could not get api version data");
    }
    $version = @json_decode($version_raw, true);
    if ($version === null) {
        die("Could not decode api version data");
    }

    $config_contents = [
        'db-type' => $dbtype,
        'db-host' => $dbhost,
        'db-user' => $dbuser,
        'db-pass' => $dbpass,
        'db-name' => $dbname,
        'cache' => $cache,
        'redis-host' => $redishost,
        'redis-port' => $redisport,
        'redis-password' => $redispassword,
        'host' => $host,
        'dir' => $dir,
        'configured' => true,
        'config_version' => CONFIG_VERSION,
        'fabric_integration' => 'on',
        'forge_integration' => 'on',
        'neoforge_integration' => 'on',
        'modrinth_integration' => 'on',
        'enable_self_updater' => 'on'
    ];

    $config_contents['api_key'] = $api_key;

    if (strtolower($version['stream']) === 'dev') {
        $config_contents['dev_builds'] = 'on';
    }

    $config->setall($config_contents);

    $conn = $db->connect();
    if ($conn) {
        if (!$db->beginTransaction(true)) {
            die('{"status":"error","message":"Could not start transaction"}');
        }
        
        $result = true;
        if ($dbtype == 'sqlite') {
            // sqlite: bigtext,varchar => text
            // int => integer
            // unsigned doesn't exist.
            $result &= $db->execute("CREATE TABLE metrics (
                name TEXT PRIMARY KEY,
                time_stamp INTEGER,
                info TEXT
            )");
            $result &= $db->execute("CREATE TABLE modpacks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                display_name TEXT,
                url TEXT,
                icon TEXT,
                icon_md5 TEXT,
                logo TEXT,
                logo_md5 TEXT,
                background TEXT,
                background_md5 TEXT,
                latest TEXT,
                recommended TEXT,
                public INTEGER,
                UNIQUE (name)
            )");
            $result &= $db->execute("CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                display_name TEXT,
                pass TEXT,
                perms TEXT,
                privileged INTEGER,
                icon TEXT,
                api_key TEXT,
                settings TEXT,
                UNIQUE (name)
            )");
            $result &= $db->execute("CREATE TABLE clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                UUID TEXT,
                UNIQUE (UUID)
            )");
            $result &= $db->execute("CREATE TABLE builds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                modpack INTEGER NOT NULL,
                name TEXT NOT NULL,
                minecraft TEXT,
                java TEXT,
                loadertype TEXT,
                memory TEXT,
                mods TEXT,
                public INTEGER
            )");
            $result &= $db->execute("CREATE TABLE mods (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                pretty_name TEXT NOT NULL,
                url TEXT,
                link TEXT,
                author TEXT,
                donlink TEXT,
                description TEXT,
                version TEXT,
                md5 TEXT,
                jar_md5 TEXT,
                mcversion TEXT,
                filename TEXT,
                filesize INTEGER,
                type TEXT,
                loadertype TEXT
            )");
            $result &= $db->execute("CREATE TABLE build_mods (
                build_id INTEGER NOT NULL,
                mod_id INTEGER NOT NULL,
                PRIMARY KEY (build_id, mod_id),
                FOREIGN KEY (build_id) REFERENCES builds(id),
                FOREIGN KEY (mod_id) REFERENCES mods(id)
            )");
            $result &= $db->execute("CREATE TABLE build_clients (
                build_id INTEGER NOT NULL,
                client_id INTEGER NOT NULL,
                PRIMARY KEY (build_id, client_id),
                FOREIGN KEY (build_id) REFERENCES builds(id),
                FOREIGN KEY (client_id) REFERENCES clients(id)
            )");
            $result &= $db->execute("CREATE TABLE modpack_clients (
                modpack_id INTEGER NOT NULL,
                client_id INTEGER NOT NULL,
                PRIMARY KEY (modpack_id, client_id),
                FOREIGN KEY (modpack_id) REFERENCES modpacks(id),
                FOREIGN KEY (client_id) REFERENCES clients(id)
            )");
        } else {
            $result &= $db->execute("CREATE TABLE metrics (
                name VARCHAR(128) PRIMARY KEY,
                time_stamp BIGINT UNSIGNED,
                info TEXT
            )");
            $result &= $db->execute("CREATE TABLE modpacks (
                id INTEGER UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128),
                display_name VARCHAR(128),
                url VARCHAR(512),
                icon VARCHAR(512),
                icon_md5 VARCHAR(32),
                logo VARCHAR(512),
                logo_md5 VARCHAR(32),
                background VARCHAR(512),
                background_md5 VARCHAR(32),
                latest VARCHAR(512),
                recommended VARCHAR(512),
                public BOOLEAN,
                UNIQUE (name)
            )");
            $result &= $db->execute("CREATE TABLE users (
                id INTEGER UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128),
                display_name VARCHAR(128),
                pass VARCHAR(128),
                perms VARCHAR(512),
                privileged BOOLEAN,
                icon LONGTEXT,
                api_key VARCHAR(128),
                settings LONGTEXT,
                UNIQUE (name)
            )");
            $result &= $db->execute("CREATE TABLE clients (
                id INTEGER UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128),
                UUID VARCHAR(128),
                UNIQUE (UUID)
            )");
            $result &= $db->execute("CREATE TABLE builds (
                id INTEGER UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                modpack INTEGER UNSIGNED NOT NULL,
                name VARCHAR(128) NOT NULL,
                minecraft VARCHAR(128),
                java VARCHAR(512),
                loadertype VARCHAR(32),
                memory VARCHAR(512),
                mods LONGTEXT,
                public BOOLEAN
            )");
            $result &= $db->execute("CREATE TABLE mods (
                id INTEGER UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128) NOT NULL,
                pretty_name VARCHAR(128) NOT NULL,
                url VARCHAR(512),
                link VARCHAR(512),
                author VARCHAR(512),
                donlink VARCHAR(512),
                description VARCHAR(1024),
                version VARCHAR(512),
                md5 VARCHAR(32),
                jar_md5 VARCHAR(32),
                mcversion VARCHAR(128),
                filename VARCHAR(128),
                filesize INTEGER,
                type VARCHAR(128),
                loadertype VARCHAR(32)
            )");
            $result &= $db->execute("CREATE TABLE build_mods (
                build_id INTEGER UNSIGNED NOT NULL,
                mod_id INTEGER UNSIGNED NOT NULL,
                PRIMARY KEY (build_id, mod_id),
                FOREIGN KEY (build_id) REFERENCES builds(id),
                FOREIGN KEY (mod_id) REFERENCES mods(id)
            )");
            $result &= $db->execute("CREATE TABLE build_clients (
                build_id INTEGER UNSIGNED NOT NULL,
                client_id INTEGER UNSIGNED NOT NULL,
                PRIMARY KEY (build_id, client_id),
                FOREIGN KEY (build_id) REFERENCES builds(id),
                FOREIGN KEY (client_id) REFERENCES clients(id)
            )");
            $result &= $db->execute("CREATE TABLE modpack_clients (
                modpack_id INTEGER UNSIGNED NOT NULL,
                client_id INTEGER UNSIGNED NOT NULL,
                PRIMARY KEY (modpack_id, client_id),
                FOREIGN KEY (modpack_id) REFERENCES modpacks(id),
                FOREIGN KEY (client_id) REFERENCES clients(id)
            )");
        }
        if (!$result) {
            die("Error while creating database tables");
        }

        // if user already exists, replace
        $userexistsq = $db->query("SELECT 1 FROM users WHERE name='".$db->sanitize($email)."'");
        if ($userexistsq && sizeof($userexistsq) == 1) { // `name` is unique
            if (OVERWRITE_USER) {
                $db->execute("DELETE FROM users WHERE name='".$db->sanitize($email)."'");
            } else {
                die("User with that email exists. Please go back and try again with different information.");
            }
        }

        $db->execute("INSERT INTO users (name,display_name,perms,privileged,pass,icon,api_key) VALUES(
            '".$db->sanitize($email)."',
            '".$db->sanitize($name)."',
            '".DEFAULT_PERMS."',
            1,
            '".$pass."',
            '".ICON."',
            '".$db->sanitize($api_key)."'
        )");

        if (!$db->commit()) {
            die('{"status":"error","message":"Could not commit changes"}');
        }   

        $db->disconnect();

        header("Location: ".substr($_SERVER['REQUEST_URI'], 0, -strlen($_SERVER['REQUEST_URI']))."login");
        exit();
    } else {
        $connection_failed = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Configure Solder</title>
        <link rel="stylesheet" href="./resources/bootstrap/bootstrap.min.css">
        <link rel="stylesheet" href="./resources/bootstrap/dark/bootstrap.min.css" media="(prefers-color-scheme: dark)">
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"
                integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49"
                crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"
                integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy"
                crossorigin="anonymous"></script>
        <script defer src="https://use.fontawesome.com/releases/v5.2.0/js/all.js"
                integrity="sha384-4oV5EgaV02iISL2ban6c/RmotsABqE4yZxZLcYMAdG7FAPsyHYAPpywE9PJo+Khy"
                crossorigin="anonymous"></script>
        <style>
            .card {
                 padding: 2em;
                 margin: 2em 0;
            }
            body {
                background-color: #f0f4f9;
            }
            @media (prefers-color-scheme: dark) {
                body {
                    background-color: #202429;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <?php
                if (isset($_GET['reconfig'])) {
                    echo "<a href='". (isset($_GET['ret']) ? $_GET['ret'] : '/') ."'><button class='btn btn-secondary'>Cancel</button></a>";
                }
if (isset($_GET['host']) && $connection_failed) {
    echo "<font class='text-danger'>Can't connect to database</font><br/>";
}
if (isset($_GET['reconfig'])) { ?>
                    <center>
                        <h1>Reconfigure</h1>
                    </center>
                <?php } else { ?>
                <center>
                    <h1>Before you start</h1>
                    <h3>You need configure Technic Solder.</h3>
                </center>
                <?php } ?>
                <form method="POST">
                    <h4>Your Account</h4>
                    <div class="form-group">
                        <label for="email">Login credentials</label>
                        <input required type="text" class="form-control" name="email" aria-describedby="emailHelp"
                               placeholder="Your Email" <?php if (getenv('ADMIN_EMAIL')) echo 'value="'.getenv('ADMIN_EMAIL').'"' ?>><br />
                        <input required type="password" class="form-control" id="pass" name="pass"
                               placeholder="Your new password" <?php if (getenv('ADMIN_PASSWORD')) echo 'value="'.getenv('ADMIN_PASSWORD').'"' ?>><br />
                        <input required type="password" class="form-control" id="pass2"
                               placeholder="Confirm your password" <?php if (getenv('ADMIN_PASSWORD')) echo 'value="'.getenv('ADMIN_PASSWORD').'"' ?>>
                        <small id="emailHelp" class="form-text text-muted">
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="name">Authoring name</label>
                        <input required type="text" class="form-control" name="author" id="name"
                               aria-describedby="nameHelp" placeholder="Your Name" <?php if (getenv('ADMIN_NAME')) echo 'value="'.getenv('ADMIN_NAME').'"' ?>>
                        <small id="nameHelp" class="form-text text-muted">
                            Visible to other users and the public. Used for custom files you add to your modpack. 
                        </small><br/>
                    </div>
                    <h4>Technic Solder API Key</h4 >
                    <div class="form-group">
                        <input required id="api_key" name="api_key" type="text" class="form-control" placeholder="API Key" <?php if (getenv('SOLDER_API_KEY')) echo 'value="'.getenv('SOLDER_API_KEY').'"' ?>>
                        <small class="form-text text-muted">
                            You can find your API Key in your profile at
                            <a target="_blank" href="https://technicpack.net">technicpack.net</a>.<br/>
                            <!-- Making your API key server-wide makes it available to all other users, and prevents them from using their own. -->
                        </small><br/>
                    </div>
                    <h4>Database</h4>
                    <div class="form-group">
                        <select required name="db-type" class="form-control" id="db-type">
                            <option value="sqlite" <?php if (getenv('DB_TYPE') !== 'mysql') echo 'selected' ?>>SQLite</option>
                            <option value="mysql" <?php if (getenv('DB_TYPE') === 'mysql') echo 'selected' ?>>MySQL</option>
                        </select>
                        <div id="mysql-options" <?php if (getenv('DB_TYPE') !== 'mysql') echo 'style="display:none;"' ?>>
                            <br/>
                            <input <?php if (strtolower(getenv('DB_TYPE')) === 'mysql') echo 'required' ?> name="db-host" type="text" class="form-control" id="db-host" placeholder="Database host" <?php if (!empty(getenv('MYSQL_HOST'))) echo 'value="'.getenv('MYSQL_HOST').'"' ?>><br />
                            <input <?php if (strtolower(getenv('DB_TYPE')) === 'mysql') echo 'required' ?> name="db-name" type="text" class="form-control" id="db-name" placeholder="Database name" <?php if (!empty(getenv('MYSQL_NAME'))) echo 'value="'.getenv('MYSQL_NAME').'"' ?>><br />
                            <input <?php if (strtolower(getenv('DB_TYPE')) === 'mysql') echo 'required' ?> name="db-user" type="text" class="form-control" id="db-user" placeholder="Database username" <?php if (!empty(getenv('MYSQL_USER'))) echo 'value="'.getenv('MYSQL_USER').'"' ?>><br />
                            <input <?php if (strtolower(getenv('DB_TYPE')) === 'mysql') echo 'required' ?> name="db-pass" type="password" class="form-control" id="db-pass" placeholder="Database password" <?php if (!empty(getenv('MYSQL_PASSWORD'))) echo 'value="'.getenv('MYSQL_PASSWORD').'"' ?>>
                        </div><br/>
                        <small class="form-text text-muted">
                            <b>MySQL is highly recommended.</b><br/>
                            <li>If migrating from original solder, <b>use a new database.</b></li>
                            <li>If MySQL was previously used, your data will not be transferred to SQLite, and vice-versa.</li>
                        </small><br/>
                    </div>
                    <h4>Caching</h4>
                    <div class="form-group">
                        <select required name="cache" class="form-control" id="cache">
                            <option value="none" <?php if (getenv('CACHE') !== 'redis') echo 'selected' ?>>none</option>
                            <option value="redis" <?php if (getenv('CACHE') === 'redis') echo 'selected' ?>>redis</option>
                        </select><br/>
                        <div id="redis-options" <?php if (getenv('CACHE') !== 'redis') echo "style='display:none;'" ?>>
                            <input <?php if (getenv('CACHE') === 'redis') echo 'required' ?> name="redis-host" type="text" class="form-control" id="redis-host"  placeholder="Redis host" <?php if (getenv('REDIS_HOST')) echo 'value="'.getenv('REDIS_HOST').'"' ?>><br/>
                            <input name="redis-port" type="text" class="form-control" id="redis-port" placeholder="Redis port" <?php if (getenv('REDIS_PORT')) echo 'value="'.getenv('REDIS_PORT').'"' ?>><br/>
                            <input name="redis-password" type="password" class="form-control" id="redis-password" placeholder="Redis password (optional)" <?php if (getenv('REDIS_PASSWORD')) echo 'value="'.getenv('REDIS_PASSWORD').'"' ?>><br/>
                        </div>
                        <small class="form-text text-muted">
                            <b>Redis is highly recommended.</b>
                        </small><br/>
                    </div>
                    <h4>Server</h4>
                    <div class="form-group">
                        <input required id="host" name="host" type="text" class="form-control" placeholder="Webserver IP or hostname" value="<?php echo $_SERVER['HTTP_HOST'] ?>" <?php if (getenv('HOST')) echo 'value="'.getenv('HOST').'"' ?>>
                        <small id="host-warning" class="form-text" style="display:none;">
                            IP/hostname should NOT start with http[s]://!
                        </small><br />
                        <input required id="dir" class="form-control" type="text" name="dir" placeholder="Install Directory" <?php if (getenv('DIR')) echo 'value="'.getenv('DIR').'"' ?>>
                        <small class="form-text text-muted">Must be '/', or start and end with a '/'.</small><br/>
                    </div>
                    <button id="save" type="submit" class="btn btn-success btn-block btn-lg">Continue</button>
                </form>
                <script type="text/javascript">
                    function validatePassword(password) {
                        const minLength = password.length >= 8;
                        const hasNumber = /[0-9]/.test(password);
                        const hasLowerCase = /[a-z]/.test(password);
                        const hasUpperCase = /[A-Z]/.test(password);

                        if (!minLength) {
                            return false;
                        }
                        if (!hasNumber || !hasUpperCase || !hasLowerCase) {
                            return false;
                        }
                        return true;
                    }

                    $("#host").on("keyup", function() {
                        let hostval = $("#host").val();
                        if (hostval.startsWith("https://") || hostval.startsWith("http://")) {
                            $("#host-warning").show();
                        } else if ($("#host-warning").is(":visible")) {
                            $("#host-warning").hide();
                        }
                    });
                    $("#pass").on("keyup", function() {
                        if (validatePassword($("#pass").val())) {
                            $("#pass").addClass("is-valid");
                            $("#pass").removeClass("is-invalid");
                        } else {
                            $("#pass").addClass("is-invalid");
                            $("#pass").removeClass("is-valid");
                        }
                        if ($("#pass2").val()==$("#pass").val() && validatePassword($("#pass2").val())) {
                            $("#pass2").addClass("is-valid");
                            $("#pass2").removeClass("is-invalid");
                            $("#pass").addClass("is-valid");
                            $("#pass").removeClass("is-invalid");
                        } else if($("#pass2").val()!="") {
                            $("#pass2").addClass("is-invalid");
                            $("#pass2").removeClass("is-valid");
                        }
                    });
                    $("#pass2").on("keyup", function() {
                        if ($("#pass2").val()==$("#pass").val() && validatePassword($("#pass2").val())) {
                            $("#pass2").addClass("is-valid");
                            $("#pass2").removeClass("is-invalid");
                        } else {
                            $("#pass2").addClass("is-invalid");
                            $("#pass2").removeClass("is-valid");
                        }
                    });
                    $('#db-type').change(function() {
                        if ($(this).val()==="sqlite") {
                            $("#db-host").removeAttr('required');
                            $("#db-user").removeAttr('required');
                            $("#db-name").removeAttr('required');
                            $("#db-pass").removeAttr('required');
                            $("#mysql-options").hide();
                            $("#errtext").hide();
                        } else {
                            $("#db-host").attr('required','required');
                            $("#db-user").attr('required','required');
                            $("#db-name").attr('required','required');
                            $("#db-pass").attr('required','required');
                            $("#mysql-options").show();
                        }
                    });
                    $("#db-pass").on("keyup", function() {
                        let http = new XMLHttpRequest();
                        let params = 'db-type='+$("#db-type").val() +'&db-pass='+ $("#db-pass").val() +'&db-name='+ $("#db-name").val() +'&db-user='+
                            $("#db-user").val() +'&db-host='+ $("#db-host").val();
                        http.open('POST', './functions/conntest.php');
                        http.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                        http.onreadystatechange = function() {
                            if (http.readyState == 4 && http.status == 200) {
                                // console.log('got response from conntest: "'+http.responseText+'"');
                                if (http.responseText == "error") {
                                    $("#errtext").text("Can't connect to database");
                                    $("#errtext").removeClass("text-muted text-success");
                                    $("#errtext").addClass("text-danger");
                                } else {
                                    $("#errtext").text("Connected to database");
                                    $("#errtext").removeClass("text-muted text-danger");
                                    $("#errtext").addClass("text-success");
                                }
                            }
                        }
                        http.send(params);
                    });

                    $('#cache').change(function() {
                        if ($(this).val() === "redis") {
                            $("#redis-host").attr('required','required')
                            $("#redis-port").attr('required','required')
                            $("#redis-password").attr('required','required')
                            $('#redis-options').show()
                        } else {
                            $("#redis-host").removeAttr('required')
                            $("#redis-port").removeAttr('required')
                            $("#redis-password").removeAttr('required')
                            $('#redis-options').hide()
                        }
                    })

                    $("#api_key").on("keyup", function() {
                        if ($("#api_key").val().length==32 && /^[a-zA-Z0-9]+$/.test($('#api_key').val())) {
                            $("#api_key").addClass("is-valid");
                            $("#api_key").removeClass("is-invalid");
                        } else {
                            $("#api_key").removeClass("is-valid");
                            $("#api_key").addClass("is-invalid");
                        }
                    });

                    $(document).ready(function() {
                        var loc = window.location.pathname;
                        var dir = loc.substring(0, loc.lastIndexOf('/'));
                        $("#dir").val(dir + "/");
                        if ($("#dir").val()=="//") {
                            $("#dir").val("/");
                        }
                    });
                </script>
            </div>
        </div>
    </body>
</html>
