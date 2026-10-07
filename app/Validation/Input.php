<?php
declare(strict_types=1);
namespace App\Validation;
final class Input
{
    public static function text(array $data, string $key): string { return is_string($data[$key] ?? null) ? trim($data[$key]) : ''; }
    public static function phone(string $value): ?string
    {
        $value = preg_replace('/[\s().-]/', '', $value);
        if (preg_match('/^[6-9]\d{9}$/', $value)) { $value = '+91' . $value; }
        elseif (preg_match('/^91[6-9]\d{9}$/', $value)) { $value = '+' . $value; }
        return preg_match('/^\+[1-9]\d{7,14}$/', $value) ? $value : null;
    }
    public static function password(string $password): bool { return mb_strlen($password) >= 12 && strlen($password) <= 72 && !str_contains($password, "\0"); }
}
