<?php
declare(strict_types=1);
namespace App\Support;
use PDO;

final class Database
{
    private static ?PDO $connection = null;
    public static function connection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = new PDO('mysql:host=' . Config::get('DB_HOST', '127.0.0.1') . ';port=' . Config::get('DB_PORT', '3306') . ';dbname=' . Config::get('DB_DATABASE') . ';charset=utf8mb4', Config::get('DB_USERNAME'), Config::get('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
            self::$connection->exec("SET time_zone = '+00:00'");
        }
        return self::$connection;
    }
}
