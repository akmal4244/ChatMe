<?php
// ChatMe - Route all requests to Laravel public/index.php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$publicPath = __DIR__ . '/public';

if ($uri !== '/' && file_exists($publicPath . $uri)) {
    return false; // Serve existing static files
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicPath . '/index.php';

require $publicPath . '/index.php';
