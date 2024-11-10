<?php
// purely a function file.
// requires $db, $config in scope.
function write_settings($settings, $user) {
    // write to db/disk settings for a user
    global $db;
    global $config;
    assert(!empty($user));
    assert(!empty($settings));
    // since for some reason admin is NOT in db •–•
    if ($user==$config['mail']) {
        if (is_dir('./functions')) {
            $path='./functions/settings.php';
        } else {
            $path='./settings.php';
        }
        @$ret = file_put_contents($path, '<?php return '.var_export($settings, true).'; ?>');
        if ($ret) {
            return TRUE;
        } else {
            error_log("failed to write admin-user settings");
            return FALSE;
        }
    }
    // for normal users, api_key is seperate from settings.
    if (isset($settings['api_key'])) {
        $usersettingsq = $db->execute("UPDATE users SET api_key='".$settings['api_key']."' WHERE name='".$db->sanitize($user)."'");
        if (!$usersettingsq) {
            error_log("set_settings(): Failed to write settings=>api_key");
            return FALSE;
        }
        unset($settings['api_key']);
    }
    $usersettingsq = $db->execute("UPDATE users SET settings='".base64_encode(json_encode($settings))."' WHERE name='".$db->sanitize($user)."'");
    if ($usersettingsq) {
        return TRUE;
    }
    error_log("set_settings(): Failed to write settings");
    return FALSE;
}
function read_settings($user) {
    // read settings for a user
    global $config;
    global $db;
    assert(!empty($user));
    // since for some reason admin is NOT in db •–•
    if ($user==$config['mail']) {
        if (!file_exists("./functions/settings.php")) {
            $_SESSION['user-settings']=[];
        } else {
            $data = require("./functions/settings.php");
            $_SESSION['user-settings'] = $data;
            error_log("got admin-user settings ".json_encode($data));
        }
    } else {
        $usersettingsq = $db->query("SELECT settings FROM users WHERE name='".$db->sanitize($user)."'");
        if ($usersettingsq && sizeof($usersettingsq)==1 && isset($usersettingsq[0]['settings'])) {
            $_SESSION['user-settings'] = json_decode(base64_decode($usersettingsq[0]['settings']),true);
            error_log("got user settings from database");
        } else {
            error_log("unable to get user settings");
        }

        // for normal users, api_key is seperate from settings.
        $userapikeyq = $db->query("SELECT api_key FROM users WHERE name='".$db->sanitize($user)."'");
        if ($userapikeyq && sizeof($userapikeyq)==1) {
            $_SESSION['user-settings']['api_key'] = $userapikeyq[0]['api_key'];
            error_log("got api key from database");
        }
    }
}
function got_settings() {
    return isset($_SESSION['user-settings']);
}
function set_setting($key,$value) {
    assert(!empty($key));
    $new_settings = isset($_SESSION['user-settings']) ? $_SESSION['user-settings'] : [];
    $new_settings[$key] = $value;
    if (write_settings($new_settings, $_SESSION['user'])) {
        $_SESSION['user-settings'] = $new_settings;
        return TRUE;
    }
    return FALSE;
}
function set_settings($multiple_settings) {
    assert(!empty($multiple_settings));
    $new_settings = isset($_SESSION['user-settings']) ? $_SESSION['user-settings'] : [];
    foreach ($multiple_settings as $key=>$value) {
        assert(!empty($key));
        $new_settings[$key] = $value;
    }
    if (write_settings($new_settings, $_SESSION['user'])) {
        $_SESSION['user-settings'] = $new_settings;
        return TRUE;
    }
    return FALSE;
}
function get_setting($key) {
    assert(!empty($key));
    if (isset($_SESSION['user-settings'][$key])) {
        return $_SESSION['user-settings'][$key];
    } else {
        error_log('get_setting: key "'.$key.'" does not exist');
        return FALSE;
    }
}
?>