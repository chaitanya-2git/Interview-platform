<?php
declare(strict_types=1);
namespace App\Support;

final class Config
{
    private static array $values = [];
    public static function load(string $file): void
    {
        if (!is_file($file)) { return; }
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) { continue; }
            if (!str_contains($line, '=')) { throw new \RuntimeException('Invalid environment entry'); }
            [$key, $value] = explode('=', $line, 2);
            self::$values[trim($key)] = trim(trim($value), "\"'");
        }
    }
    public static function get(string $key, string $default = ''): string
    {
        $external = getenv($key);
        return $external !== false ? $external : (self::$values[$key] ?? $default);
    }
    public static function basePath(): string { return rtrim((string) parse_url(self::get('APP_URL', 'http://localhost:8000'), PHP_URL_PATH), '/'); }
    public static function iniBytes(string|false $value):int
    {
        if($value===false)return 0;$value=trim($value);if($value==='')return 0;$number=(float)$value;$unit=strtolower(substr($value,-1));return (int)round($number*match($unit){'g'=>1024**3,'m'=>1024**2,'k'=>1024,default=>1});
    }
    public static function key(): string
    {
        $key = self::get('APP_KEY');
        if (!preg_match('/^[a-f0-9]{64}$/i', $key)) { throw new \RuntimeException('Configure APP_KEY'); }
        return $key;
    }
}
