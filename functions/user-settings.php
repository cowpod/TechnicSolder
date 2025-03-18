<?php
// purely a function file.
// requires $db
function write_settings($settings, $user): bool {
    // write to db/disk settings for a user
    global $db;
    assert(!empty($user));
    assert(!empty($settings));
    // api_key is stored in a seperate field
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
function read_settings($user): void {
    // read settings for a user
    global $db;
    assert(!empty($user));
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
function got_settings(): bool {
    return isset($_SESSION['user-settings']);
}
function set_setting($key,$value): bool {
    assert(!empty($key));
    $new_settings = isset($_SESSION['user-settings']) ? $_SESSION['user-settings'] : [];
    $new_settings[$key] = $value;
    if (write_settings($new_settings, $_SESSION['user'])) {
        $_SESSION['user-settings'] = $new_settings;
        return TRUE;
    }
    return FALSE;
}
function set_settings($multiple_settings): bool {
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