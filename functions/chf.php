<?php
if (empty($_GET['link'])) {
    die("No link provided");
}

$headers=get_headers($_GET['link']);
if (stripos($headers[0], "200 OK")) {
    echo "OK";
} else {
    echo "ERR";
}
exit();