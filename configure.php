<?php
error_reporting(E_ALL & ~E_NOTICE);
session_start();

$config=['configured'=>false];
if (file_exists('./functions/config.php')) {
    $config = include("./functions/config.php");
}

$settings = include("./functions/settings.php");
if (!isset($_GET['reconfig'])) {
    if ($config['configured']) {
        sleep(1);
        header("Location: ".$config['dir']."login");
        exit();
    }
} else {
    if (!isset($_SESSION['user'])) {
        die("You need to be logged in!");
    }
    if ($_SESSION['user']!==$config['mail']) {
        die("insufficient permission!");
    }
}

require_once("./functions/db.php");
$db=new Db;

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
                    echo "<a href='./dashboard'><button class='btn btn-secondary'>Cancel</button></a>";
                }
                if (isset($_POST['host'])) {
                    $cf = '<?php return array( "configured" => true, ';
                    // OLD HASHING METHOD (INSECURE)
                    // $_POST['pass'] = hash("sha256",$_POST['pass']."Solder.cf");
                    $_POST['pass'] = password_hash($_POST['pass'], PASSWORD_DEFAULT);
                    $_POST['encrypted'] = true;
                    foreach ($_POST as $key => $value) {
                        $cf .= "'".$key."' => '".$value."'";
                        if ($key !== "encrypted") {
                            $cf .= ",";
                        }
                    }
                    if ($cf." );" !== "<?php return array(  );") {
                        file_put_contents("./functions/config.php", $cf." );");
                    }
                    
                    $connection_result = $db->connect();
                    if ($connection_result) {
                        if ($_POST['db-type']=='sqlite') {
                            // sqlite: bigtext,varchar => text
                            // int => integer
                            // unsigned doesn't exist.
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
                                public BOOLEAN,
                                clients TEXT,
                                UNIQUE (name));";
                            $db->query($sql);
                            $sql = "CREATE TABLE users (
                                id INTEGER PRIMARY KEY AUTOINCREMENT,
                                name TEXT,
                                display_name TEXT,
                                pass TEXT,
                                perms TEXT,
                                icon TEXT,
                                UNIQUE (name));";
                            $db->query($sql);
                            $sql = "CREATE TABLE clients (
                                id INTEGER PRIMARY KEY AUTOINCREMENT,
                                name TEXT,
                                UUID TEXT,
                                UNIQUE (UUID));";
                            $db->query($sql);
                            $sql = "CREATE TABLE builds (
                                id INTEGER PRIMARY KEY AUTOINCREMENT,
                                modpack INTEGER NOT NULL,
                                name TEXT NOT NULL,
                                minecraft TEXT,
                                java TEXT,
                                loadertype TEXT,
                                memory TEXT,
                                mods TEXT,
                                public BOOLEAN,
                                clients TEXT);";
                            $db->query($sql);
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
                                type TEXT,
                                loadertype TEXT);";
                            $db->query($sql);
                        } else {
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
                            $db->query($sql);
                            $sql = "CREATE TABLE users (
                                id int(8) AUTO_INCREMENT PRIMARY KEY,
                                name VARCHAR(128),
                                display_name VARCHAR(128),
                                pass VARCHAR(128),
                                perms VARCHAR(512),
                                icon LONGTEXT,
                                UNIQUE (name));";
                            $db->query($sql);
                            $sql = "CREATE TABLE clients (
                                id int(8) AUTO_INCREMENT PRIMARY KEY,
                                name VARCHAR(128),
                                UUID VARCHAR(128),
                                UNIQUE (UUID));";
                            $db->query($sql);
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
                            $db->query($sql);
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
                                type VARCHAR(128),
                                loadertype VARCHAR(32));";
                            $db->query($sql);
                        }

                        $db->disconnect();

                        header("Location: ".substr($_SERVER['REQUEST_URI'], 0, -strlen($_SERVER['REQUEST_URI']))."login");
                        exit();
                    } else {
                        echo "<font class='text-danger'>Can't connect to database</font><br/>";
                    }
                } ?>
                <center>
                    <h1>Before you start</h1>
                    <h3>You need configure Technic Solder.</h3>
                </center>
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input required type="text" class="form-control" name="author" id="name"
                               aria-describedby="nameHelp" placeholder="Your Name">
                        <small id="nameHelp" class="form-text text-muted">
                            Author of custom files you add to your modpack.
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="email">Login Credentials</label>
                        <input required type="text" class="form-control" name="mail" aria-describedby="emailHelp"
                               placeholder="Your Email"><br />
                        <input required type="password" class="form-control" id="pass" name="pass"
                               placeholder="Your new password"><br />
                        <input required type="password" class="form-control" id="pass2"
                               placeholder="Confirm your password">
                        <small id="emailHelp" class="form-text text-muted">
                            You will use these to log in to Technic Solder.
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="email">Database</label>
                        <small class="form-text text-muted">
                            If you already have installed the original version of solder, do not use the same database.
                            You can migrate your data later. It's recommended to use an empty database.
                        </small>
                        <label for="db-type">Database type</label>
                        <select required name="db-type" class="form-control" id="db-type">
                            <option value="mysql" selected>MySQL</option>
                            <option value="sqlite">SQLite</option>
                        </select><br />
                        <input required name="db-host" type="text" class="form-control" id="db-host"
                               placeholder="Database IP" value="127.0.0.1"><br />
                        <input required name="db-user" type="text" class="form-control" id="db-user"
                               placeholder="Database username"><br />
                        <input required name="db-name" type="text" class="form-control" id="db-name"
                               placeholder="Database name"><br />
                        <input name="db-pass" type="password" class="form-control" id="db-pass"
                               placeholder="Database password">
                        <small id="errtext" class="form-text text-muted">
                            Five tables will be created: users, clients, modpacks, builds, mods
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="email">Installation details</label>
                        <input required name="host" type="text" class="form-control"
                               placeholder="Webserver public IP or domain name. (does NOT start with http[s]://)"><br />
                        <input class="form-control" type="text" name="dir"
                               placeholder="Install Directory" value="/" id="dir" required><br />
                        <input required name="api_key" type="text" class="form-control" placeholder="API Key">
                        <small class="form-text text-muted">
                            You can find you API Key in your profile at
                            <a target="_blank" href="https://technicpack.net">technicpack.net</a>
                        </small>
                    </div>
                    <button id="save" type="submit" class="btn btn-success btn-block btn-lg">Save</button>
                </form>
                <script type="text/javascript">
                    $(document).ready(function() {
                        var loc = window.location.pathname;
                        var dir = loc.substring(0, loc.lastIndexOf('/'));
                        $("#dir").val(dir + "/");
                        if ($("#dir").val()=="//") {
                            $("#dir").val("/");
                        }

                    });
                    $("#pass2").on("keyup", function() {
                        console.log($("#pass2").val()+"=="+$("#pass").val())
                        if ($("#pass2").val()==$("#pass").val()) {
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
                            $("#db-host").hide();
                            $("#db-user").hide();
                            $("#db-name").hide();
                            $("#db-pass").hide();
                        } else {
                            $("#db-host").attr('required','required');
                            $("#db-user").attr('required','required');
                            $("#db-name").attr('required','required');
                            $("#db-pass").attr('required','required');
                            $("#db-host").show();
                            $("#db-user").show();
                            $("#db-name").show();
                            $("#db-pass").show();
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
                </script>
            </div>
        </div>
    </body>
</html>
