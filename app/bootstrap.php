<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) { require $file; }
    }
});
App\Support\Config::load(ROOT . '/.env');
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php.log');
set_exception_handler(static function (Throwable $error): void {
    $reference = bin2hex(random_bytes(6));
    // Do not persist exception messages: database errors may contain submitted PII.
    error_log($reference . ' ' . get_class($error) . ' ' . $error->getFile() . ':' . $error->getLine());
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "Operation failed. Reference: $reference. Check configuration and logs.\n"); exit(1); }
    http_response_code(500);
    App\Support\View::render('error', ['title' => 'Something went wrong', 'message' => 'Please try again shortly. Reference: ' . $reference], 'guest');
});
