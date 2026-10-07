<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\Database;
use PDO;
final class AuditService
{
    public function __construct(private readonly ?PDO $db = null) {}

    public function record(?int $actor, string $action, string $entity, ?int $id, array $metadata = []): void
    {
        $query = ($this->db ?? Database::connection())->prepare('INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, metadata_json) VALUES (?, ?, ?, ?, ?)');
        $query->execute([$actor, $action, $entity, $id, json_encode($metadata, JSON_THROW_ON_ERROR)]);
    }
}
