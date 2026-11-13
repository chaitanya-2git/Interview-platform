<?php
declare(strict_types=1);
namespace App\Support;

/** Firebase web config and the page-level CSP that Firebase Auth (Google popup, reCAPTCHA) needs. */
final class Firebase
{
    public static function configured(): bool
    {
        return Config::get('FIREBASE_PROJECT_ID') !== '' && Config::get('FIREBASE_API_KEY') !== '' && Config::get('FIREBASE_AUTH_DOMAIN') !== '';
    }

    /** @return array{apiKey:string,authDomain:string,projectId:string} */
    public static function config(): array
    {
        return ['apiKey' => Config::get('FIREBASE_API_KEY'), 'authDomain' => Config::get('FIREBASE_AUTH_DOMAIN'), 'projectId' => Config::get('FIREBASE_PROJECT_ID')];
    }

    /** Replaces the global CSP for the current page only. */
    public static function sendContentSecurityPolicy(): void
    {
        if (!self::configured()) { return; }
        $authDomain = preg_replace('/[^a-z0-9.-]/i', '', Config::get('FIREBASE_AUTH_DOMAIN'));
        header("Content-Security-Policy: default-src 'self'; "
            . "script-src 'self' https://www.gstatic.com https://www.google.com https://www.recaptcha.net https://apis.google.com; "
            . "style-src 'self' 'unsafe-inline'; img-src 'self' data: https://www.gstatic.com https://www.google.com; "
            . "connect-src 'self' https://identitytoolkit.googleapis.com https://securetoken.googleapis.com https://www.googleapis.com https://www.google.com; "
            . "frame-src https://www.google.com https://www.recaptcha.net https://recaptcha.google.com https://{$authDomain}; "
            . "object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    }
}
