<?php
declare(strict_types=1);
namespace App\Services;

use DateInterval;
use DateTimeZone;
use PDO;

final class ResourceAssignmentService
{
    public function __construct(private readonly PDO $db) {}

    public function choose(string $type, array $slot, ?int $excludeInterviewId = null, bool $locking = false): ?array
    {
        $utc = new DateTimeZone('UTC');
        $dayStart = $slot['start_local']->setTime(0, 0)->setTimezone($utc);
        $dayEnd = $slot['start_local']->setTime(0, 0)->add(new DateInterval('P1D'))->setTimezone($utc);
        $exclude = $excludeInterviewId ?? 0;
        $sql = "SELECT r.id, r.name,
                    (SELECT COUNT(*) FROM interviews day_i
                     WHERE day_i.resource_id = r.id AND day_i.status = 'BOOKED'
                       AND day_i.start_time < ? AND day_i.end_time > ?
                       AND (? = 0 OR day_i.id <> ?)) AS load_count
                FROM resources r
                WHERE r.resource_type = ? AND r.active = 1
                  AND NOT EXISTS (
                      SELECT 1 FROM interviews conflict_i
                      WHERE conflict_i.resource_id = r.id AND conflict_i.status = 'BOOKED'
                        AND conflict_i.start_time < ? AND conflict_i.end_time > ?
                        AND (? = 0 OR conflict_i.id <> ?)
                  )
                ORDER BY load_count ASC, r.id ASC LIMIT 1" . ($locking ? ' FOR UPDATE' : '');
        $query = $this->db->prepare($sql);
        $query->execute([
            $dayEnd->format('Y-m-d H:i:s'), $dayStart->format('Y-m-d H:i:s'), $exclude, $exclude,
            $type, $slot['end_utc']->format('Y-m-d H:i:s'), $slot['start_utc']->format('Y-m-d H:i:s'), $exclude, $exclude,
        ]);
        return $query->fetch() ?: null;
    }
}
