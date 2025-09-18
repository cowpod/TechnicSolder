<?php

function sanitize_string_utf8(null|string $input): null|string
{
    if (is_null($input)) {
        return $input;
    }
    // Normalize and remove invalid UTF-8 sequences
    $input = mb_convert_encoding($input, 'UTF-8', 'UTF-8');

    // Remove invisible/control characters (except newline, carriage return, tab)
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);

    // Remove HTML, PHP tags
    $input = strip_tags($input);

    // Decode HTML entities (e.g., &lt; becomes <)
    $input = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $input;
}
