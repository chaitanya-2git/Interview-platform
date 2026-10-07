<?php
declare(strict_types=1);
namespace App\Support;

final class Session
{
    public static function start(): void
    {
        $savePath = Config::get('SESSION_SAVE_PATH', 'storage/sessions');
        if ($savePath !== '') {
            $absolute = preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $savePath) === 1
                ? $savePath
                : ROOT . '/' . ltrim(str_replace('\\\\', '/', $savePath), '/');
            if (!is_dir($absolute) || !is_writable($absolute)) {
                throw new \RuntimeException('Session storage is unavailable');
            }
            session_save_path($absolute);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('blujay_session');
        session_set_cookie_params(['lifetime' => 0, 'path' => Config::basePath() . '/', 'secure' => str_starts_with(Config::get('APP_URL'), 'https://'), 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
        $now = time();
        if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > max(5, (int) Config::get('SESSION_IDLE_MINUTES', '30')) * 60 || $now - ($_SESSION['authenticated_at'] ?? $now) > 43200)) {
            self::clear();
            self::flash('Your session expired. Please sign in again.');
        }
        $_SESSION['last_activity'] = $now;
    }
    public static function clear(): void { $_SESSION = []; session_regenerate_id(true); }
    public static function flash(string $message): void { $_SESSION['flash'] = $message; }
    public static function takeFlash(): ?string { $message = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $message; }
    public static function token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
    public static function validToken(mixed $value): bool { return is_string($value) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $value); }
}
