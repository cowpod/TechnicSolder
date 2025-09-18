<?php

/**
 * @return (mixed|string)[]
 *
 * @psalm-return array{big: mixed|string, small: mixed|string}
 */
function number_suffix_string($number): array
{
    $number_big = $number;
    $number_small = $number;

    if ($number > 99999999) {
        $number_big = number_format($number / 1000000, 0)."M";
        $number_small = number_format($number / 1000000000, 1)."G";
    } elseif ($number > 9999999) {
        $number_big = number_format($number / 1000000, 0)."M";
        $number_small = number_format($number / 1000000, 0)."M";
    } elseif ($number > 999999) {
        $number_big = number_format($number / 1000000, 1)."M";
        $number_small = number_format($number / 1000000, 1)."M";
    } elseif ($number > 99999) {
        $number_big = number_format($number / 1000, 0)."K";
        $number_small = number_format($number / 1000000, 1)."M";
    } elseif ($number > 9999) {
        $number_big = number_format($number / 1000, 1)."K";
        $number_small = number_format($number / 1000, 0)."K";
    } elseif ($number > 999) {
        $number_big = number_format($number, 0);
        $number_small = number_format($number / 1000, 1)."K";
    } elseif ($number > 99) {
        $number_big = number_format($number, 0);
        $number_small = number_format($number / 1000, 1)."K";
    }

    return ['big' => $number_big,'small' => $number_small];
}

function formatSizeUnits($bytes): string
{
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }
    return $bytes;
}
