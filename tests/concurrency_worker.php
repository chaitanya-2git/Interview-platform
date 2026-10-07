<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\{AuditService, AvailabilityService, BookingService, BusinessHoursService, ResourceAssignmentService};
use App\Support\{Config, Database, FrozenClock};

if (Config::get('APP_ENV') !== 'testing' || !str_contains(strtolower(Config::get('DB_DATABASE')), 'test')) {
    throw new RuntimeException('Concurrency worker may run only against a dedicated test database.');
}

$payload = json_decode(base64_decode($argv[1] ?? '', true) ?: '', true, flags: JSON_THROW_ON_ERROR);
$waitUntil = (float) ($payload['wait_until'] ?? 0);
while (microtime(true) < $waitUntil) { usleep(1000); }
$db = Database::connection();
$clock = new FrozenClock(new DateTimeImmutable($payload['now']));
$hours = new BusinessHoursService($db, $clock);
$availability = new AvailabilityService($db, $hours, new ResourceAssignmentService($db));
$booking = new BookingService($db, $availability, $hours, new AuditService($db));
try {
    $id = $booking->create((int) $payload['candidate_id'], $payload['input']);
    echo json_encode(['ok' => true, 'id' => $id], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'type' => get_class($error), 'message' => $error->getMessage()], JSON_THROW_ON_ERROR);
}
