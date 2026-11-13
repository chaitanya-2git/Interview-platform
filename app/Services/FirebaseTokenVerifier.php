<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\Config;
use RuntimeException;

/**
 * Verifies Firebase Authentication ID tokens without the Admin SDK.
 * Follows https://firebase.google.com/docs/auth/admin/verify-id-tokens#verify_id_tokens_using_a_third-party_jwt_library
 */
final class FirebaseTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
    private const CLOCK_SKEW = 60;

    /** @return array<string,mixed> verified claims */
    public function verify(string $idToken): array
    {
        $projectId = Config::get('FIREBASE_PROJECT_ID');
        if ($projectId === '') { throw new RuntimeException('Firebase is not configured'); }
        $parts = explode('.', $idToken);
        if (count($parts) !== 3 || strlen($idToken) > 4096) { throw new RuntimeException('Malformed token'); }
        [$header, $payload] = [self::json($parts[0]), self::json($parts[1])];
        $signature = self::base64url($parts[2]);
        if (($header['alg'] ?? null) !== 'RS256' || !is_string($header['kid'] ?? null)) { throw new RuntimeException('Unexpected token header'); }

        $certificate = $this->certificates()[$header['kid']] ?? null;
        if ($certificate === null) { $certificate = $this->certificates(true)[$header['kid']] ?? null; }
        if ($certificate === null) { throw new RuntimeException('Unknown signing key'); }
        if (openssl_verify($parts[0] . '.' . $parts[1], $signature, $certificate, OPENSSL_ALGO_SHA256) !== 1) { throw new RuntimeException('Invalid token signature'); }

        $now = time();
        $valid = ($payload['aud'] ?? null) === $projectId
            && ($payload['iss'] ?? null) === 'https://securetoken.google.com/' . $projectId
            && is_string($payload['sub'] ?? null) && $payload['sub'] !== '' && strlen($payload['sub']) <= 128
            && is_int($payload['exp'] ?? null) && $payload['exp'] > $now - self::CLOCK_SKEW
            && is_int($payload['iat'] ?? null) && $payload['iat'] <= $now + self::CLOCK_SKEW
            && is_int($payload['auth_time'] ?? null) && $payload['auth_time'] <= $now + self::CLOCK_SKEW;
        if (!$valid) { throw new RuntimeException('Invalid token claims'); }
        return $payload;
    }

    /** @return array<string,string> key id => PEM certificate */
    private function certificates(bool $refresh = false): array
    {
        $cacheFile = ROOT . '/storage/cache/firebase-certs.json';
        if (!$refresh && is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && ($cached['expires'] ?? 0) > time() && is_array($cached['keys'] ?? null)) { return $cached['keys']; }
        }
        $context = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $body = @file_get_contents(self::CERTS_URL, false, $context);
        $headers = $http_response_header ?? [];
        $keys = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($keys) || $keys === [] || !str_contains($headers[0] ?? '', ' 200')) { throw new RuntimeException('Unable to fetch Firebase signing keys'); }
        $maxAge = 3600;
        foreach ($headers as $line) { if (preg_match('/^cache-control:.*max-age=(\d+)/i', $line, $match)) { $maxAge = (int) $match[1]; } }
        if (!is_dir(dirname($cacheFile))) { @mkdir(dirname($cacheFile), 0750, true); }
        @file_put_contents($cacheFile, json_encode(['expires' => time() + $maxAge, 'keys' => $keys]), LOCK_EX);
        return $keys;
    }

    private static function json(string $segment): array
    {
        $decoded = json_decode(self::base64url($segment), true);
        if (!is_array($decoded)) { throw new RuntimeException('Malformed token'); }
        return $decoded;
    }

    private static function base64url(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) { throw new RuntimeException('Malformed token'); }
        return $decoded;
    }
}
