<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\AdminRepository;
use App\Services\{AuthService, InterviewLocationService};
use App\Support\{Database, Scheduling, Session, View};
use App\Validation\ValidationException;
use DateTimeImmutable;

/** Nightly routine: assign each booked interview to an office (KPHB, Ameerpet, ...). */
final class AdminLocationController
{
    public function index(): void
    {
        $scheduling = new Scheduling();
        $hours = $scheduling->hours();
        $timezone = $hours->timezone();
        $now = $hours->nowUtc()->setTimezone($timezone);
        $service = new InterviewLocationService();
        $pending = $service->pendingByDate($timezone);
        $requested = is_string($_GET['date'] ?? null) ? $_GET['date'] : '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $requested, $timezone);
        if (!$date || $date->format('Y-m-d') !== $requested) {
            // Evening check (from 6 PM) plans tomorrow; otherwise the next day that still needs locations, or today.
            $date = $pending ? DateTimeImmutable::createFromFormat('!Y-m-d', $pending[0]['date'], $timezone) : $now->setTime(0, 0);
            if ((int) $now->format('H') >= 18 && $date <= $now) { $date = $now->modify('+1 day')->setTime(0, 0); }
        }
        $rows = (new AdminRepository(Database::connection()))->calendar($date, $date->modify('+1 day'));
        usort($rows, static fn($a, $b) => [$a['location_id'] !== null, $a['start_time']] <=> [$b['location_id'] !== null, $b['start_time']]);
        View::render('admin/locations', ['title' => 'Assign locations', 'user' => (new AuthService())->user(), 'date' => $date, 'today' => $now->setTime(0, 0),
            'rows' => $scheduling->presenter()->many($rows), 'locations' => $service->locations(), 'pending' => $pending, 'pendingTotal' => $service->pendingCount()]);
    }

    public function assign(): void
    {
        $admin = (new AuthService())->user();
        $id = (int) filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $raw = $_POST['location_id'] ?? '';
        $locationId = is_string($raw) && ctype_digit($raw) && (int) $raw > 0 ? (int) $raw : null;
        try {
            $name = (new InterviewLocationService())->assign((int) $admin['id'], $id, $locationId);
            Session::flash($name ? "Location set to {$name}." : 'Location cleared.');
        } catch (ValidationException $error) {
            Session::flash((string) (array_values($error->errors)[0] ?? 'The location could not be saved.'));
        }
        $back = is_string($_POST['return'] ?? null) && preg_match('#^admin/(locations(\?date=\d{4}-\d{2}-\d{2})?|interview\?id=\d+)(\#[a-z0-9-]+)?$#', $_POST['return']) ? $_POST['return'] : 'admin/interview?id=' . $id;
        View::redirect($back);
    }
}
