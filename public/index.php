<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// 🎯 ບັງຄັບໃສ່ Header CORS ແບບກຳປັ້ນທຸບດິນ ໃຫ້ຜ່ານທຸກ Domain
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With, X-CSRF-TOKEN");

// ຖ້າເປັນ Request ແບບ OPTIONS (Preflight) ໃຫ້ຕອບກັບ 200 OK ແລ້ວຢຸດເຮັດວຽກທັນທີ
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
