<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Repositories\InterviewRepository;
use App\Services\{AuditService, AvailabilityService, BookingService, BusinessHoursService, ResourceAssignmentService};
use App\Support\{Config, Database, FrozenClock};
use App\Validation\ValidationException;

if (Config::get('APP_ENV') !== 'testing' || !str_contains(strtolower(Config::get('DB_DATABASE')), 'test')) {
    throw new RuntimeException('Phase 2 integration tests require APP_ENV=testing and a dedicated database whose name contains "test".');
}
$db = Database::connection();
$checks = 0;
function verify(bool $condition, string $message): void { global $checks; if (!$condition) { fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL); throw new RuntimeException('FAIL: ' . $message); } $checks++; }
function rejection(callable $operation, string $contains): void {
    global $checks;
    try { $operation(); } catch (ValidationException $error) { verify(str_contains(implode(' ', $error->errors), $contains), 'Expected rejection containing: ' . $contains); return; }
    fwrite(STDERR, 'FAIL: Expected ValidationException containing: ' . $contains . PHP_EOL); throw new RuntimeException('FAIL: Expected ValidationException containing: ' . $contains);
}
function services(PDO $db, string $now = '2030-01-07T02:30:00+00:00'): array {
    $clock = new FrozenClock(new DateTimeImmutable($now));
    $hours = new BusinessHoursService($db, $clock);
    $availability = new AvailabilityService($db, $hours, new ResourceAssignmentService($db));
    return [$hours, $availability, new BookingService($db, $availability, $hours, new AuditService($db))];
}
function bookingInput(int $technologyId, string $date, string $time, int $duration = 30, string $type = 'NORMAL', ?int $support = null): array {
    return ['company_name' => 'Acme Systems', 'job_role' => 'Platform Engineer', 'technology_id' => (string) $technologyId, 'interview_round' => 'Technical Round 1', 'hr_availability' => 'available', 'hr_name' => 'Recruiter One', 'hr_phone' => '+919999999999', 'interview_type' => $type, 'support_person_id' => $support ? (string) $support : '', 'duration' => (string) $duration, 'date' => $date, 'time' => $time];
}
function candidate(PDO $db, int $technologyId, int $number): int {
    $query = $db->prepare("INSERT INTO users (role, full_name, phone, password_hash) VALUES ('CANDIDATE', ?, ?, ?)");
    $query->execute(['Test Candidate ' . $number, '+91880000' . str_pad((string) $number, 4, '0', STR_PAD_LEFT), password_hash('TestingPassword12', PASSWORD_DEFAULT)]);
    $id = (int) $db->lastInsertId();
    $query = $db->prepare("INSERT INTO candidate_profiles (user_id, technology_id, classification) VALUES (?, ?, 'Genuine')"); $query->execute([$id, $technologyId]); return $id;
}
function concurrent(array $jobs): array {
    $processes = []; $start = microtime(true) + 0.8;
    foreach ($jobs as $job) {
        $job['wait_until'] = $start; $job['now'] = '2030-01-07T02:30:00+00:00';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/concurrency_worker.php') . ' ' . escapeshellarg(base64_encode(json_encode($job, JSON_THROW_ON_ERROR)));
        $pipes = []; $process = proc_open($command, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, dirname(__DIR__));
        if (!is_resource($process)) { throw new RuntimeException('Could not start concurrency worker'); }
        fclose($pipes[0]); $processes[] = [$process, $pipes];
    }
    $results = [];
    foreach ($processes as [$process, $pipes]) { $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process); if ($code !== 0) { throw new RuntimeException('Concurrency worker failed: ' . $err); } $results[] = json_decode($out, true, flags: JSON_THROW_ON_ERROR); }
    return $results;
}

[$hours, $availability, $booking] = services($db);
$db->exec("INSERT INTO technologies (name, active) VALUES ('Test Java',1),('Inactive Legacy',0)");
$technologyId = (int) $db->query("SELECT id FROM technologies WHERE name='Test Java'")->fetchColumn();
$inactiveTechnology = (int) $db->query("SELECT id FROM technologies WHERE name='Inactive Legacy'")->fetchColumn();
$hari = (int) $db->query("SELECT id FROM support_people WHERE name='Hari'")->fetchColumn();
$arjun = (int) $db->query("SELECT id FROM support_people WHERE name='Arjun'")->fetchColumn();
$sahithi = (int) $db->query("SELECT id FROM support_people WHERE name='Sahithi'")->fetchColumn();
$candidates = []; for ($i = 1; $i <= 20; $i++) { $candidates[$i] = candidate($db, $technologyId, $i); }

$normal30 = $availability->availableSlots($candidates[1], ['date'=>'2030-01-07','duration'=>'30','interview_type'=>'NORMAL','support_person_id'=>'']);
$normal45 = $availability->availableSlots($candidates[1], ['date'=>'2030-01-07','duration'=>'45','interview_type'=>'NORMAL','support_person_id'=>'']);
$normal60 = $availability->availableSlots($candidates[1], ['date'=>'2030-01-07','duration'=>'60','interview_type'=>'NORMAL','support_person_id'=>'']);
verify(count($normal30['slots']) === 35 && end($normal30['slots'])['start'] === '18:30', '30-minute availability boundaries');
verify(count($normal45['slots']) === 34 && end($normal45['slots'])['start'] === '18:15', '45-minute availability boundaries');
verify(count($normal60['slots']) === 33 && end($normal60['slots'])['start'] === '18:00', '60-minute availability boundaries');
verify($availability->availableSlots($candidates[1], ['date'=>'2030-01-06','duration'=>'30','interview_type'=>'NORMAL','support_person_id'=>''])['slots'] === [], 'Sunday returns no slots');
rejection(fn() => $booking->create($candidates[1], bookingInput($technologyId,'2030-01-06','10:00')), 'closed');
rejection(fn() => $booking->create($candidates[1], bookingInput($technologyId,'2030-01-07','18:15',60)), 'outside business hours');
rejection(fn() => $booking->create($candidates[1], bookingInput($technologyId,'2030-01-07','10:07',30)), '15-minute');
rejection(fn() => $booking->create($candidates[1], bookingInput($technologyId,'not-a-date','10:00',30)), 'valid interview date');

$id30 = $booking->create($candidates[1], bookingInput($technologyId,'2030-01-07','10:00',30));
$id45 = $booking->create($candidates[2], bookingInput($technologyId,'2030-01-07','10:00',45));
rejection(fn() => $booking->create($candidates[3], bookingInput($technologyId,'2030-01-07','10:00',60)), 'No Normal computer');
$resources = $db->query("SELECT resource_id FROM interviews WHERE id IN ($id30,$id45) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
verify(count(array_unique($resources)) === 2, 'overlap assigns distinct resources');
rejection(fn() => $booking->create($candidates[1], bookingInput($technologyId,'2030-01-07','10:15',30)), 'another interview');
$backToBack = $booking->create($candidates[3], bookingInput($technologyId,'2030-01-07','10:30',30));
verify($backToBack > 0, 'back-to-back resource booking allowed');
$id60 = $booking->create($candidates[4], bookingInput($technologyId,'2030-01-07','13:00',60));
verify((int)$db->query("SELECT duration_minutes FROM interviews WHERE id=$id60")->fetchColumn() === 60, '60-minute booking created');

$tool1 = $booking->create($candidates[5], bookingInput($technologyId,'2030-01-07','11:00',60,'TOOL',$hari));
rejection(fn() => $booking->create($candidates[6], bookingInput($technologyId,'2030-01-07','11:15',30,'TOOL',$hari)), 'Hari is already assigned');
$tool2 = $booking->create($candidates[6], bookingInput($technologyId,'2030-01-07','11:00',60,'TOOL',$arjun));
verify((int)$db->query("SELECT resource_id FROM interviews WHERE id=$tool1")->fetchColumn() !== (int)$db->query("SELECT resource_id FROM interviews WHERE id=$tool2")->fetchColumn(), 'Tool PC 2 assigned when Tool PC 1 occupied');
rejection(fn() => $booking->create($candidates[7], bookingInput($technologyId,'2030-01-07','11:15',30,'TOOL',$sahithi)), 'No Tool computer');
$backHari = $booking->create($candidates[7], bookingInput($technologyId,'2030-01-07','12:00',30,'TOOL',$hari));
verify($backHari > 0, 'back-to-back support-person booking allowed');
$supportRelease = $booking->create($candidates[19], bookingInput($technologyId,'2030-01-08','13:00',30,'TOOL',$hari));
rejection(fn() => $booking->create($candidates[20], bookingInput($technologyId,'2030-01-08','13:00',30,'TOOL',$hari)), 'Hari is already assigned');
$booking->cancel($candidates[19], $supportRelease);
verify($booking->create($candidates[20], bookingInput($technologyId,'2030-01-08','13:00',30,'TOOL',$hari)) > 0, 'cancelled booking releases support person');

rejection(fn() => $booking->create($candidates[8], bookingInput($inactiveTechnology,'2030-01-08','10:00')), 'technology');
$db->prepare('UPDATE support_people SET active=0 WHERE id=?')->execute([$sahithi]);
rejection(fn() => $booking->create($candidates[8], bookingInput($technologyId,'2030-01-08','10:00',30,'TOOL',$sahithi)), 'support person');
$db->prepare('UPDATE support_people SET active=1 WHERE id=?')->execute([$sahithi]);
rejection(fn() => $booking->create($candidates[8], bookingInput($technologyId,'2030-01-08','10:00',30,'TOOL',null)), 'support person');
rejection(fn() => $booking->create($candidates[8], bookingInput($technologyId,'2030-01-08','10:00',30,'NORMAL',$hari)), 'do not use');

$normal2 = (int)$db->query("SELECT id FROM resources WHERE name='Normal PC 2'")->fetchColumn();
$db->prepare('UPDATE resources SET active=0 WHERE id=?')->execute([$normal2]);
$release = $booking->create($candidates[8], bookingInput($technologyId,'2030-01-08','10:00',30));
rejection(fn() => $booking->create($candidates[9], bookingInput($technologyId,'2030-01-08','10:00',30)), 'No Normal computer');
$booking->cancel($candidates[8], $release);
$afterCancel = $booking->create($candidates[9], bookingInput($technologyId,'2030-01-08','10:00',30));
verify($afterCancel > 0, 'cancelled booking releases resource');
$db->prepare('UPDATE resources SET active=1 WHERE id=?')->execute([$normal2]);

$booking->reschedule($candidates[1], $id30, ['date'=>'2030-01-09','time'=>'10:00','duration'=>'45','support_person_id'=>'']);
$moved = $db->query("SELECT start_time,duration_minutes FROM interviews WHERE id=$id30")->fetch();
verify($moved['start_time'] === '2030-01-09 04:30:00' && (int)$moved['duration_minutes'] === 45, 'reschedule succeeds atomically');
$booking->create($candidates[10], bookingInput($technologyId,'2030-01-09','11:00',60));
$booking->create($candidates[11], bookingInput($technologyId,'2030-01-09','11:00',60));
rejection(fn() => $booking->reschedule($candidates[1], $id30, ['date'=>'2030-01-09','time'=>'11:00','duration'=>'30','support_person_id'=>'']), 'No Normal computer');
verify($db->query("SELECT start_time FROM interviews WHERE id=$id30")->fetchColumn() === '2030-01-09 04:30:00', 'failed reschedule preserves original');
$immutableType = $booking->create($candidates[20], bookingInput($technologyId,'2030-01-11','10:00',30));
$booking->reschedule($candidates[20], $immutableType, ['date'=>'2030-01-11','time'=>'11:00','duration'=>'30','support_person_id'=>'','interview_type'=>'TOOL']);
verify($db->query("SELECT interview_type FROM interviews WHERE id=$immutableType")->fetchColumn() === 'NORMAL', 'reschedule ignores client-supplied type');

[$lateHours,, $lateBooking] = services($db, '2030-01-09T03:45:00+00:00');
verify(!$lateHours->canCandidateChange('2030-01-09 04:30:00'), 'change cutoff evaluated with fixed clock');
rejection(fn() => $lateBooking->cancel($candidates[1], $id30), 'cutoff');
$repository = new InterviewRepository($db);
verify($repository->candidateDetail($candidates[2], $id30) === null, 'candidate ownership prevents IDOR');
verify($repository->adminDetail($id30) !== null, 'admin repository sees booking');
verify(count($repository->adminSearch(['search'=>'Acme'], $hours->timezone())) >= 1, 'admin search returns booking');

[$advanceHours,, $advanceBooking] = services($db, '2030-01-07T06:00:00+00:00'); // 11:30 IST
rejection(fn() => $advanceBooking->create($candidates[12], bookingInput($technologyId,'2030-01-07','12:15',30)), 'in advance');
$atCutoff = $advanceBooking->create($candidates[12], bookingInput($technologyId,'2030-01-07','12:30',30));
verify($atCutoff > 0, 'booking exactly at advance cutoff allowed');

$db->prepare('UPDATE resources SET active=0 WHERE id=?')->execute([$normal2]);
$jobs = [
    ['candidate_id'=>$candidates[13], 'input'=>bookingInput($technologyId,'2030-01-10','10:00',30)],
    ['candidate_id'=>$candidates[14], 'input'=>bookingInput($technologyId,'2030-01-10','10:00',30)],
];
$results = concurrent($jobs); verify(count(array_filter($results, fn($r) => $r['ok'])) === 1, 'concurrent final Normal resource has one winner');
$db->prepare('UPDATE resources SET active=1 WHERE id=?')->execute([$normal2]);

$results = concurrent([
    ['candidate_id'=>$candidates[15], 'input'=>bookingInput($technologyId,'2030-01-10','11:00',30,'TOOL',$hari)],
    ['candidate_id'=>$candidates[16], 'input'=>bookingInput($technologyId,'2030-01-10','11:00',30,'TOOL',$hari)],
]);
verify(count(array_filter($results, fn($r) => $r['ok'])) === 1, 'concurrent Hari conflict has one winner');
$results = concurrent([
    ['candidate_id'=>$candidates[17], 'input'=>bookingInput($technologyId,'2030-01-10','12:00',30,'TOOL',$hari)],
    ['candidate_id'=>$candidates[18], 'input'=>bookingInput($technologyId,'2030-01-10','12:00',30,'TOOL',$arjun)],
]);
verify(count(array_filter($results, fn($r) => $r['ok'])) === 2, 'concurrent different support people both succeed');
verify((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('booking.created','booking.cancelled','booking.rescheduled')")->fetchColumn() >= 1, 'booking lifecycle audited');

echo $checks . " Phase 2 checks passed.\n";
