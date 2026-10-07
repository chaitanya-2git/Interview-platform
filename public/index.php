<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store');
if (str_starts_with(App\Support\Config::get('APP_URL'), 'https://')) { header('Strict-Transport-Security: max-age=31536000'); }
App\Support\Session::start();
$router = new App\Support\Router();
require ROOT . '/routes/web.php';
$router->dispatch();
