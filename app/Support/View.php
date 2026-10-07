<?php
declare(strict_types=1);
namespace App\Support;

final class View
{
    public static function escape(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    public static function url(string $path = ''): string { return Config::basePath() . '/' . ltrim($path, '/'); }
    public static function redirect(string $path): never { header('Location: ' . self::url($path), true, 303); exit; }
    public static function render(string $template, array $data = [], string $layout = 'app'): void
    {
        extract($data, EXTR_SKIP);
        $e = self::escape(...);
        $url = self::url(...);
        ob_start();
        require ROOT . '/app/Views/' . $template . '.php';
        $content = ob_get_clean();
        require ROOT . '/app/Views/layouts/' . $layout . '.php';
    }
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    }
}
