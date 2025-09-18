<?php

require_once('sanitize.php');

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    //$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) {
        return 'n-a';
    }
    return $text;
}

function slugify2(string $str): string
{
    $ret = '';
    $str_length = strlen($str);
    for ($i = 0; $i < $str_length; $i++) {
        if ($str[$i] == '.' || $str[$i] == '-' || ctype_alnum($str[$i])) {
            $ret .= $str[$i];
        }
    }
    $ret = trim($ret, '-.');
    if (empty($ret)) {
        return 'n-a';
    }
    return strtolower($ret);
}

function slugify3(string $str): string
{
    // meant for full filenames
    return strtolower(preg_replace('/[^\w\-\.]/', '-', $str));
}
