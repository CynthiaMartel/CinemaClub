<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Detecta si vendor/ está en el nivel estándar (local/dev)
// o dos niveles arriba en ../laravel/ (Hostinger: public_html/backend-api/ → laravel/)
$laravelRoot = is_dir(__DIR__ . '/../vendor')
    ? __DIR__ . '/../'
    : __DIR__ . '/../../laravel/';

if (file_exists($maintenance = $laravelRoot . 'storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravelRoot . 'vendor/autoload.php';

/** @var Application $app */
$app = require_once $laravelRoot . 'bootstrap/app.php';

$app->handleRequest(Request::capture());
