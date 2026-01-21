<?php
require_once('transliterate.php');

/**
 * Transliterate string, and drop unknown chars except for regex word chars.
 * Optionally specify $unknown_replace to a non-empty char value to replace instead of dropping.
 * @param string $str - string to slugify.
 * @param string $unknown_replace - default "" to drop, set to another char to replace instead.
 * @return string - slugified string, may be blank "".
 */
function slugify(string $str, string $unknown_replace = ''): string|false {
    // transliterate
    $trl = transliterate($str);

    // drop chars transliterate() missed, and also 
    return preg_replace('/[^\w_\-]/', $unknown_replace, $trl);
}
/**
 * Transliterate string, and drop unknown chars except for regex word chars. Does not drop period.
 * Optionally specify $unknown_replace to a non-empty char value to replace instead of dropping.
 * @param string $str - string to slugify.
 * @param string $unknown_replace - default "" to drop, set to another char to replace instead.
 * @return string - slugified string, may be blank "".
 */
function slugify2(string $str, string $unknown_replace = ''): string|false {
    // transliterate
    $trl = transliterate($str);

    // drop chars transliterate() missed, and also 
    return preg_replace('/[^\w_\-\.]/', $unknown_replace, $trl);
}


/**
 * Validate if string is a valid slug.
 * @param string $str - string to validate
 * @return bool - true if valid, false if invalid.
 */
function validate_slug(string $str): bool {
    return strspn($str, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_') === strlen($str);
}
