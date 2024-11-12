<?php
define('CONFIG_VERSION', 1);
define('ICON', "iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAB9ElEQVR4Xu2bSytEcRiHZyJRaDYWRhJilFlYKjakNOWS7OxEGCRGpAg1KykRSlHSKLkO0YyFhSiRIQmbIcVEsnCXW/EJPB/g9Jvt0/8s3t73+b3nnDnmpZWaXxP8dssRm6yL+XTc9OO1Ib+9GWCe60BuyUpEvvDYiNysAqgDNAJygCSoFPi/AoaPwbCvXnRAKKoZc/T7rA/5kasEeV1wEvlJnBf5lM+KfD16mPcAFUAdoBGQA8gSkqBSwOAxmBZ8QQdsOTIwRzsPOae7Iy/w/Op3DvLwZd4zgrYnPJ83Xcp7gAqgDtAIyAFkCUlQKWDwGKzdPeUH//ftmKPz9ePIQ6m1yANufq+QPteK58s6tpHvRZTxHqACqAM0AnIAWkISVAoYOwaf13bQAZn2WSzAQ1EB38/3FyP/9R0jz/K/I/cMxSM3VSTzHqACqAM0AnIAWUISVAoYPAbfe6/RAV07b5ijH/uFyD8Dd8jnejy8R+TwnuG8GsTzpXdJvAeoAOoAjYAcQJaQBJUCBo9B+6sDHfDSUoM5Wm1uQ34Z60YeMzOB3DJygNy5yU+sHGNNvAeoAOoAjYAcQJaQBJUCBo/B7Cr+aMrvnMEctVbx9wCVXbxINboS8Pqu0DnyFDf//2B0o4H3ABVAHaARwD1ADpAElQKGjsE/aSRgFj7BEuwAAAAASUVORK5CYII=");
define('DEFAULT_PERMS', '1111111'); // and 'privileged'=>'1' makes you an admin.
define('OVERWRITE_USER', TRUE);
session_start();

$config=['configured'=>false];
if (file_exists('./functions/config.php')) {
    $config = include("./functions/config.php");
}
$settings=[];
if (file_exists('./functions/settings.php')) {
    $settings = include("./functions/settings.php");
}
if (isset($config['configured']) && $config['configured']) {
    error_log("configure.php: already configured, redirecting to login");
    header("Location: ".$config['dir']."login");
    exit();
} elseif (isset($_GET['reconfig'])) {
    error_log("configure.php: reconfiguring");
    if (!isset($_SESSION['user'])) {
        die("You need to be logged in!");
    }
    if (!$_SESSION['privileged']) {
        die("Insufficient permission!");
    }
}

require_once("./functions/db.php");
$db=new Db;

$connection_failed=FALSE;

if (isset($_POST['host'])) {
    // OLD HASHING METHOD (INSECURE)
    // $_POST['pass'] = hash("sha256",$_POST['pass']."Solder.cf");
    $_POST['pass'] = password_hash($_POST['pass'], PASSWORD_DEFAULT);

    // todo: hash password client-side
    
    $email = strtolower($_POST['email']);
    $name = $_POST['author'];
    $pass = $_POST['pass'];
    $api_key = $_POST['api_key'];

    if (!preg_match('/^[\w\.\-\+]+@[a-zA-Z\d\.-]+\.[a-zA-Z]{2,}$/', $email)) {
        die('Bad input data; email');
    }
    if (!preg_match("/^[a-zA-Z\s\-\.\s]+$/", $name)) {
        die("Bad input data; name");
    }
    // if (!preg_match("/^[a-zA-Z0-9+\/]+={,2}$/", $pass)) {
    //     die("Bad input data; pass");
    // }
    if (!ctype_alnum($api_key) || strlen($api_key)!=32) {
        die("Bad input data; api_key");
    }

    $dbtype = strtolower($_POST['db-type']);
    $dbhost = strtolower($_POST['db-host']);
    $dbuser = $_POST['db-user'];
    $dbpass = $_POST['db-pass'];
    $dbname = $_POST['db-name'];
    $host = strtolower($_POST['host']);
    $dir = $_POST['dir'];

    if (!ctype_alnum($dbtype)||!ctype_alnum($dbuser)||!ctype_alnum($dbname)) {
        die("Bad input data; db type/user/name");
    }
    if (!preg_match("/^[a-z0-9\.\-]+$/", $dbhost) || !preg_match("/^[a-z0-9\.\-]+$/", $host)) {
        die("Bad input data; host,db host");
    }

    $config_contents = [
        'db-type'=>strtolower($dbtype),
        'db-host'=>strtolower($dbhost),
        'db-user'=>strtolower($dbuser),
        'db-pass'=>strtolower($dbpass),
        'db-name'=>strtolower($dbname),
        'host'=>strtolower($host),
        'dir'=>strtolower($dir),
        'configured'=>true,
        'config_version'=>CONFIG_VERSION
    ];

    file_put_contents('./functions/config.php', '<?php return '.var_export($config_contents, true).' ?>');

    $conn = $db->connect();
    if ($conn) {
        if ($_POST['db-type']=='sqlite') {
            // sqlite: bigtext,varchar => text
            // int => integer
            // unsigned doesn't exist.
            $sql = "CREATE TABLE metrics (
                name TEXT PRIMARY KEY,
                time_stamp INTEGER,
                info TEXT);";
            $db->execute($sql);
            $sql = "CREATE TABLE modpacks (
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
                clients TEXT,
                UNIQUE (name));";
            $db->execute($sql);
            $sql = "CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                display_name TEXT,
                pass TEXT,
                perms TEXT,
                privileged INTEGER,
                icon TEXT,
                api_key TEXT,
                settings TEXT,
                UNIQUE (name));";
            $db->execute($sql);
            $sql = "CREATE TABLE clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                UUID TEXT,
                UNIQUE (UUID));";
            $db->execute($sql);
            $sql = "CREATE TABLE builds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                modpack INTEGER NOT NULL,
                name TEXT NOT NULL,
                minecraft TEXT,
                java TEXT,
                loadertype TEXT,
                memory TEXT,
                mods TEXT,
                public INTEGER,
                clients TEXT);";
            $db->execute($sql);
            $sql = "CREATE TABLE mods (
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
                mcversion TEXT,
                filename TEXT,
                filesize INTEGER,
                type TEXT,
                loadertype TEXT);";
            $db->execute($sql);
        } else {
            $sql = "CREATE TABLE metrics (
                name VARCHAR(128) PRIMARY KEY,
                time_stamp BIGINT UNSIGNED,
                info TEXT";
            $db->execute($sql);
            $sql = "CREATE TABLE modpacks (
                id int(64) AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128),
                display_name VARCHAR(128),
                url VARCHAR(512),
                icon VARCHAR(512),
                icon_md5 VARCHAR(512),
                logo VARCHAR(512),
                logo_md5 VARCHAR(512),
                background VARCHAR(512),
                background_md5 VARCHAR(512),
                latest VARCHAR(512),
                recommended VARCHAR(512),
                public BOOLEAN,
                clients LONGTEXT,
                UNIQUE (name));";
            $db->execute($sql);
            $sql = "CREATE TABLE users (
                id int(8) AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128),
                display_name VARCHAR(128),
                pass VARCHAR(128),
                perms VARCHAR(512),
                privileged BOOLEAN,
                icon LONGTEXT,
                api_key VARCHAR(128),
                settings LONGTEXT,
                UNIQUE (name));";
            $db->execute($sql);
            $sql = "CREATE TABLE clients (
                id int(8) AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128),
                UUID VARCHAR(128),
                UNIQUE (UUID));";
            $db->execute($sql);
            $sql = "CREATE TABLE builds (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                modpack INT(6) NOT NULL,
                name VARCHAR(128) NOT NULL,
                minecraft VARCHAR(128),
                java VARCHAR(512),
                loadertype VARCHAR(32),
                memory VARCHAR(512),
                mods VARCHAR(1024),
                public BOOLEAN,
                clients LONGTEXT);";
            $db->execute($sql);
            $sql = "CREATE TABLE mods (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128) NOT NULL,
                pretty_name VARCHAR(128) NOT NULL,
                url VARCHAR(512),
                link VARCHAR(512),
                author VARCHAR(512),
                donlink VARCHAR(512),
                description VARCHAR(1024),
                version VARCHAR(512),
                md5 VARCHAR(512),
                mcversion VARCHAR(128),
                filename VARCHAR(128),
                filesize INTEGER,
                type VARCHAR(128),
                loadertype VARCHAR(32));";
            $db->execute($sql);
        }

        // if user already exists, replace
        $userexistsq = $db->query("SELECT 1 FORM users WHERE name='".$db->sanitize($email)."'");
        if ($userexistsq && sizeof($userexistsq)==1) { // `name` is unique
            if (OVERWRITE_USER) {
                $db->execute("DELETE FORM users WHERE name='".$db->sanitize($email)."'");
            } else {
                die("User with that email exists. Please go back and try again with different information.");
            }
        }

        $adduserq = $db->execute("INSERT INTO users (name,display_name,perms,privileged,pass,icon,api_key) VALUES(
            '".$db->sanitize($email)."',
            '".$db->sanitize($name)."',
            '".DEFAULT_PERMS."',
            1,
            '".$pass."',
            '".ICON."',
            '".$db->sanitize($api_key)."'
        )");

        $db->disconnect();

        header("Location: ".substr($_SERVER['REQUEST_URI'], 0, -strlen($_SERVER['REQUEST_URI']))."login");
        exit();
    } else {
        $connection_failed=TRUE;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Configure Solder</title>
        <?php if (isset($_SESSION['dark']) && $_SESSION['dark']=="on") {
            echo '<link rel="stylesheet" href="https://bootswatch.com/4/superhero/bootstrap.min.css">';
        } else {
            echo '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css"
        integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">';
        } ?>
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
        </style>
    </head>
    <body style="<?php if (isset($_SESSION['dark']) && $_SESSION['dark']=="on") {
        echo "background-color: #202429";
    } else {
        echo "background-color: #f0f4f9";
    } ?>">
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
                               placeholder="Your Email"><br />
                        <input required type="password" class="form-control" id="pass" name="pass"
                               placeholder="Your new password"><br />
                        <input required type="password" class="form-control" id="pass2"
                               placeholder="Confirm your password">
                        <small id="emailHelp" class="form-text text-muted">
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="name">Authoring name</label>
                        <input required type="text" class="form-control" name="author" id="name"
                               aria-describedby="nameHelp" placeholder="Your Name">
                        <small id="nameHelp" class="form-text text-muted">
                            Visible to other users and the public. Used for custom files you add to your modpack. 
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="api_key">Technic Solder API Key</label>
                        <input id="api_key" name="api_key" type="text" class="form-control" placeholder="API Key" required>
                        <small class="form-text text-muted">
                            You can find your API Key in your profile at
                            <a target="_blank" href="https://technicpack.net">technicpack.net</a>.
                        </small>
                    </div>
                    <h4>Database</h4>
                    <div class="form-group">
                        <select required name="db-type" class="form-control" id="db-type">
                            <option value="mysql" <?php if (!empty($_POST['db-type'])&&$_POST['db-type']=='mysql') echo 'selected' ?>>MySQL</option>
                            <option value="sqlite" <?php if (!empty($_POST['db-type'])&&$_POST['db-type']=='sqlite') echo 'selected' ?>>SQLite</option>
                        </select>
                        <small id="sqlite-warning" style="display:none" class="form-text">
                            <b>SQLite is not recommended for large installations.</b>
                        </small>
                        <div id="mysql-options">
                            <br/>
                            <input required name="db-host" type="text" class="form-control" id="db-host"
                                   placeholder="Database IP" value="127.0.0.1"><br />
                            <input required name="db-user" type="text" class="form-control" id="db-user"
                                   placeholder="Database username"><br />
                            <input required name="db-name" type="text" class="form-control" id="db-name"
                                   placeholder="Database name"><br />
                            <input name="db-pass" type="password" class="form-control" id="db-pass"
                                   placeholder="Database password">
                        </div>
                        <small class="form-text text-muted">
                            <li>If migrating from original solder, <b>use a new database.</b></li>
                            <li>If MySQL was previously used, your data will not be transferred to SQLite, and vice-versa.</li>
                        </small>
                        <small id="errtext" class="form-text text-muted">
                            Five tables will be created: users, clients, modpacks, builds, mods
                        </small>
                    </div>
                    <h4>Server</h4>
                    <div class="form-group">
                        <input id="host" name="host" type="text" class="form-control"
                               placeholder="Webserver IP or hostname" value="<?php echo $_SERVER['HTTP_HOST'] ?>" required>
                        <small id="host-warning" class="form-text" style="display:none;">
                            IP/hostname should NOT start with http[s]://!
                        </small><br />
                        <input id="dir" class="form-control" type="text" name="dir"
                               placeholder="Install Directory" value="/" required><br />
                    </div>
                    <button id="save" type="submit" class="btn btn-success btn-block btn-lg" disabled>Save</button>
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
                            $("#save").attr("disabled", true);
                        }
                        if ($("#pass2").val()==$("#pass").val() && validatePassword($("#pass2").val())) {
                            $("#pass2").addClass("is-valid");
                            $("#pass2").removeClass("is-invalid");
                            $("#pass").addClass("is-valid");
                            $("#pass").removeClass("is-invalid");
                            $("#save").attr("disabled", false);
                        } else if($("#pass2").val()!="") {
                            $("#pass2").addClass("is-invalid");
                            $("#pass2").removeClass("is-valid");
                            $("#save").attr("disabled", true);
                        }
                    });
                    $("#pass2").on("keyup", function() {
                        if ($("#pass2").val()==$("#pass").val() && validatePassword($("#pass2").val())) {
                            $("#pass2").addClass("is-valid");
                            $("#pass2").removeClass("is-invalid");
                            $("#save").attr("disabled", false);
                        } else {
                            $("#pass2").addClass("is-invalid");
                            $("#pass2").removeClass("is-valid");
                            $("#save").attr("disabled", true);
                        }
                    });
                    $('#db-type').change(function() {
                        if ($(this).val()=="sqlite") {
                            $("#db-host").removeAttr('required');
                            $("#db-user").removeAttr('required');
                            $("#db-name").removeAttr('required');
                            $("#db-pass").removeAttr('required');
                            $("#mysql-options").hide();
                            $("#sqlite-warning").show();
                            $("#save").attr("disabled", false);
                            $("#errtext").hide();
                        } else {
                            $("#db-host").attr('required','required');
                            $("#db-user").attr('required','required');
                            $("#db-name").attr('required','required');
                            $("#db-pass").attr('required','required');
                            $("#mysql-options").show();
                            $("#sqlite-warning").hide();
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
                                    $("#save").attr("disabled", true);
                                } else {
                                    $("#errtext").text("Connected to database");
                                    $("#errtext").removeClass("text-muted text-danger");
                                    $("#errtext").addClass("text-success");
                                    $("#save").attr("disabled", false);
                                }
                            }
                        }
                        http.send(params);
                    });
                    $("#api_key").on("keyup", function() {
                        if ($("#api_key").val().length==32 && /^[a-zA-Z0-9]+$/.test($('#api_key').val())) {
                            $("#api_key").addClass("is-valid");
                            $("#api_key").removeClass("is-invalid");
                            $("#save").attr("disabled", false);
                        } else {
                            $("#api_key").removeClass("is-valid");
                            $("#api_key").addClass("is-invalid");
                            $("#save").attr("disabled", true);
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
