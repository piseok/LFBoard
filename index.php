<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// 공유호스팅에서 도큐먼트 루트를 public/ 하위로 지정할 수 없어 저장소 전체를 그대로 웹 루트에
// 올리는 배포 구조(main 브랜치)에서는 이 파일이 public/이 아니라 저장소 루트에 위치하게 된다.
// vendor/의 위치로 현재 이 파일이 어디 있는지 스스로 판별해 두 구조 모두에서 동작하게 한다.
$basePath = is_dir(__DIR__.'/../vendor') ? __DIR__.'/..' : __DIR__;

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $basePath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
