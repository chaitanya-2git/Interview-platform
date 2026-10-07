<?php
// PHP development server only: php -S localhost:8000 -t public public/router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$assetPath = $path;
// Let local QA exercise a one-segment production base path such as /interviews.
if (preg_match('#^/[^/]+(/assets/.+)$#', $path, $match)) { $assetPath = $match[1]; }
$file = realpath(__DIR__ . $assetPath);
if ($file !== false && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    $types=['css'=>'text/css; charset=utf-8','js'=>'application/javascript; charset=utf-8','jpg'=>'image/jpeg','png'=>'image/png'];$extension=strtolower(pathinfo($file,PATHINFO_EXTENSION));
    header('Content-Type: '.($types[$extension]??'application/octet-stream'));header('Content-Length: '.filesize($file));readfile($file);exit;
}
require __DIR__ . '/index.php';
