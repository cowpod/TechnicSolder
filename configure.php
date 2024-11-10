<?php
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

$connection_result=false;
if (isset($_POST['host'])) {
    $cf = '<?php return array( "configured" => true, ';
    // OLD HASHING METHOD (INSECURE)
    // $_POST['pass'] = hash("sha256",$_POST['pass']."Solder.cf");
    $_POST['pass'] = password_hash($_POST['pass'], PASSWORD_DEFAULT);
    $_POST['encrypted'] = true;

    foreach ($_POST as $key => $value) {
        if ($key=="api_key") continue; // we're putting it in admin-user's settings.php
        $cf .= "'".$key."' => '".$value."'";
        if ($key !== "encrypted") {
            $cf .= ",";
        }
    }
    if ($cf." );" !== "<?php return array(  );") {
        file_put_contents("./functions/config.php", $cf." );");
    }

    file_put_contents("./functions/settings.php", "<?php return array('api_key'=>'".$_POST['api_key']."') ?>");

    $connection_result = $db->connect();

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
            public BOOLEAN,
            clients TEXT,
            UNIQUE (name));";
        $db->execute($sql);
        $sql = "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            display_name TEXT,
            pass TEXT,
            perms TEXT,
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
            public BOOLEAN,
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
            type TEXT,
            loadertype TEXT);";
        $db->execute($sql);
    } else {
        $sql = "CREATE TABLE metrics (
            name VARCHAR(128) PRIMARY KEY,
            time_stamp int(64),
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
            type VARCHAR(128),
            loadertype VARCHAR(32));";
        $db->execute($sql);
    }

    $db->disconnect();

    header("Location: ".substr($_SERVER['REQUEST_URI'], 0, -strlen($_SERVER['REQUEST_URI']))."login");
    exit();
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
                if (isset($_GET['host'])&&!$connection_result) {
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
                        <input required type="text" class="form-control" name="mail" aria-describedby="emailHelp"
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
                        <input name="api_key" type="text" class="form-control" placeholder="API Key" required>
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
                    $("#host").on("keyup", function() {
                        let hostval = $("#host").val();
                        if (hostval.startsWith("https://") || hostval.startsWith("http://")) {
                            $("#host-warning").show();
                        } else if ($("#host-warning").is(":visible")) {
                            $("#host-warning").hide();
                        }
                    });
                    $("#pass2").on("keyup", function() {
                        // console.log($("#pass2").val()+"=="+$("#pass").val())
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
                </script>
            </div>
        </div>
    </body>
</html>
