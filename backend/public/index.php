<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Register the auto loader
|--------------------------------------------------------------------------
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilise it into the application.
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run the application
|--------------------------------------------------------------------------
*/

/** @var Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
