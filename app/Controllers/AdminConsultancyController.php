<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{AuthService, ConsultancyService};
use App\Support\{Scheduling, Session, View};
use App\Validation\{Input, ValidationException};

final class AdminConsultancyController
{
    public function index(): void
    {
        $service = new ConsultancyService();
        $status = strtoupper(is_string($_GET['status'] ?? null) ? $_GET['status'] : '');
        View::render('admin/consultancies', ['title' => 'Consultancies', 'user' => (new AuthService())->user(),
            'status' => in_array($status, ConsultancyService::STATUSES, true) ? $status : '',
            'counts' => $service->counts(), 'consultancies' => $service->directory($status)]);
    }

    public function detail(): void
    {
        $service = new ConsultancyService();
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $consultancy = $id ? $service->find((int) $id) : null;
        if (!$consultancy) { http_response_code(404); View::render('error', ['title' => 'Consultancy not found', 'message' => 'The requested consultancy does not exist.', 'user' => (new AuthService())->user()]); return; }
        $hours = (new Scheduling())->hours();
        $local = $hours->nowUtc()->setTimezone($hours->timezone());
        $from = is_string($_GET['from'] ?? null) && $_GET['from'] !== '' ? $_GET['from'] : $local->modify('first day of this month')->format('Y-m-d');
        $to = is_string($_GET['to'] ?? null) && $_GET['to'] !== '' ? $_GET['to'] : $local->format('Y-m-d');
        View::render('admin/consultancy', ['title' => $consultancy['name'], 'user' => (new AuthService())->user(), 'consultancy' => $consultancy,
            'students' => $service->students((int) $consultancy['id']), 'from' => $from, 'to' => $to,
            'report' => $service->report((int) $consultancy['id'], $hours->timezone(), $from, $to)]);
    }

    public function review(): void
    {
        $admin = (new AuthService())->user();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        try {
            $status = (new ConsultancyService())->review((int) $admin['id'], (int) $id, (is_string($_GET['do'] ?? null) ? $_GET['do'] : ''), Input::text($_POST, 'note'));
            Session::flash('Consultancy ' . strtolower($status) . '.');
        } catch (ValidationException $error) {
            Session::flash((string) (array_values($error->errors)[0] ?? "Something went wrong."));
        }
        View::redirect('admin/consultancy?id=' . (int) $id);
    }
}
