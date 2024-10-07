<?php
function number_suffix_string($number) {
    $number_big = $number;
    $number_small = $number;

    if ($number>99999999) {
        $number_big = number_format($number/1000000,0)."M";
        $number_small = number_format($number/1000000000,1)."G";
    } elseif ($number>9999999) {
        $number_big = number_format($number/1000000,0)."M";
        $number_small = number_format($number/1000000,0)."M";
    } elseif ($number>999999) {
        $number_big = number_format($number/1000000,1)."M";
        $number_small = number_format($number/1000000,1)."M";
    } elseif ($number>99999) {
        $number_big = number_format($number/1000,0)."K";
        $number_small = number_format($number/1000000,1)."M";
    } elseif ($number>9999) {
        $number_big = number_format($number/1000,1)."K";
        $number_small = number_format($number/1000,0)."K";
    } elseif ($number>999) {
        $number_big = number_format($number,0);
        $number_small = number_format($number/1000,1)."K";
    } elseif ($number>99) {
        $number_big = number_format($number,0);
        $number_small = number_format($number/1000,1)."K";
    }
    
    return ['big'=>$number_big,'small'=>$number_small];
}