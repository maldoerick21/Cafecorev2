<?php
session_start();
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Controller.php';
require_once APP_ROOT . '/app/core/App.php';
spl_autoload_register(function (string $class) {
    $paths = [
        APP_ROOT . '/app/controllers/' . $class . '.php',
        APP_ROOT . '/app/models/' . $class . '.php',
        APP_ROOT . '/app/core/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});