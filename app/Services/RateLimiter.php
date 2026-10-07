<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\{Config, Database};
final class RateLimiter
{
    // Atomic increment precedes authentication: concurrent attempts cannot bypass the limit.
    public function allow(string $scope, string $identity, int $limit, int $seconds = 900): bool
    {
        $db = Database::connection();
        $hash = hash_hmac('sha256', $scope . ':' . $identity, Config::key());
        $query = $db->prepare('INSERT INTO rate_limits (bucket_hash, attempts, expires_at) VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE attempts = IF(expires_at <= UTC_TIMESTAMP(), 1, attempts + 1), expires_at = IF(expires_at <= UTC_TIMESTAMP(), VALUES(expires_at), expires_at)');
        $query->execute([$hash, $seconds]);
        $query = $db->prepare('SELECT attempts FROM rate_limits WHERE bucket_hash = ?');
        $query->execute([$hash]);
        return (int) $query->fetchColumn() <= $limit;
    }
}
