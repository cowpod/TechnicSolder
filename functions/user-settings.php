<?php
// purely a function file.
// requires $db to be defined and initialized
assert(array_key_exists('db', $GLOBALS) || array_key_exists('db', get_defined_vars()));
assert($db->status());

require_once('sanitize.php');

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
    $encoded_json = @json_encode($settings);
    if ($encoded_json === false) {
        error_log('Could not encode settings to json');
        return FALSE;
    }
    $encoded_b64 = base64_encode($encoded_json);

    $usersettingsq = $db->execute("UPDATE users SET settings='{$encoded_b64}' WHERE name='{$db->sanitize($user)}'");
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
        $settings_json = @base64_decode($usersettingsq[0]['settings']);
        if ($settings_json===false) {
            error_log("failed to decode base64 user settings");
        }
        $settings = @json_decode($settings_json, true);
        if ($settings===null) {
            error_log("failed to decode json user settings");
        }
        $_SESSION['user-settings'] = $settings;
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