<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\Database;
use App\Validation\ValidationException;
use DateTimeImmutable;
use DateTimeZone;

/** Which BluJay office (KPHB, Ameerpet, ...) a booked candidate should go to. */
final class InterviewLocationService
{
    public function locations(bool $activeOnly = true): array
    {
        return Database::connection()->query('SELECT id, name, address, active FROM interview_locations' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, name')->fetchAll();
    }

    /** Assigns (or clears, with null) the location of a booked interview. */
    public function assign(int $adminId, int $interviewId, ?int $locationId): ?string
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT i.id, i.status, i.location_id, l.name FROM interviews i LEFT JOIN interview_locations l ON l.id = i.location_id WHERE i.id = ? FOR UPDATE');
            $query->execute([$interviewId]);
            $interview = $query->fetch();
            if (!$interview) { throw new ValidationException(['form' => 'Interview not found.']); }
            if ($interview['status'] !== 'BOOKED') { throw new ValidationException(['form' => 'Locations can only be set for booked interviews.']); }
            $name = null;
            if ($locationId !== null) {
                $query = $db->prepare('SELECT name FROM interview_locations WHERE id = ? AND active = 1');
                $query->execute([$locationId]);
                $name = $query->fetchColumn();
                if (!$name) { throw new ValidationException(['form' => 'Choose an active location.']); }
            }
            $db->prepare('UPDATE interviews SET location_id = ?, location_assigned_by = ?, location_assigned_at = ' . ($locationId === null ? 'NULL' : 'UTC_TIMESTAMP()') . ' WHERE id = ?')
                ->execute([$locationId, $locationId === null ? null : $adminId, $interviewId]);
            (new AuditService($db))->record($adminId, $locationId === null ? 'admin.interview.location_cleared' : 'admin.interview.location_assigned', 'interview', $interviewId, ['from' => $interview['name'], 'to' => $name]);
            $db->commit();
            return $name ?: null;
        } catch (\Throwable $error) {
            if ($db->inTransaction()) { $db->rollBack(); }
            throw $error;
        }
    }

    /** Upcoming booked interviews that still need a location. */
    public function pendingCount(): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM interviews WHERE status = 'BOOKED' AND location_id IS NULL AND end_time >= UTC_TIMESTAMP()")->fetchColumn();
    }

    /** Pending counts per local date, for the next days with unassigned interviews. @return list<array{date:string,total:int}> */
    public function pendingByDate(DateTimeZone $timezone, int $limit = 14): array
    {
        $offset = (new DateTimeImmutable('now', $timezone))->format('P');
        $query = Database::connection()->prepare("SELECT DATE(CONVERT_TZ(start_time, '+00:00', ?)) day, COUNT(*) total FROM interviews WHERE status = 'BOOKED' AND location_id IS NULL AND end_time >= UTC_TIMESTAMP() GROUP BY day ORDER BY day LIMIT " . max(1, min(60, $limit)));
        $query->execute([$offset]);
        return array_map(static fn($r) => ['date' => (string) $r['day'], 'total' => (int) $r['total']], $query->fetchAll());
    }

    public static function badge(array $row): string
    {
        if (($row['status'] ?? '') !== 'BOOKED') { return ''; }
        $name = $row['location_name'] ?? null;
        if (is_string($name) && $name !== '') { return '<span class="location-badge" title="Interview location">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>'; }
        $ended = isset($row['end_time']) && strtotime($row['end_time'] . ' UTC') < time();
        return $ended ? '' : '<span class="location-badge pending" title="Location not assigned yet">To be assigned</span>';
    }
}
