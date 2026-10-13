<?php

$uri = urldecode(parse_url($_SERVER['REQUES_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

require __DIR__ . '/index.php';
