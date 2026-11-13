<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\{CandidateTechnologyRepository, UserRepository};
use App\Services\{AuthService, ConsultancyService};
use App\Support\{Database, NotFoundException, Scheduling, Session, View};
use App\Validation\ValidationException;

/** Partner consultancy workspace: students, bookings on their behalf, and reports. */
final class ConsultancyController
{
    private ConsultancyService $service;
    private array $user;
    private array $consultancy;

    public function __construct()
    {
        $this->service = new ConsultancyService();
        $this->user = (new AuthService())->user() ?? [];
        $consultancy = $this->user ? $this->service->forUser((int) $this->user['id']) : null;
        if ($consultancy === null) {
            http_response_code(403);
            View::render('error', ['title' => 'Consultancy not found', 'message' => 'This account is not linked to a consultancy.'], 'guest');
            exit;
        }
        $this->consultancy = $consultancy;
    }

    public function home(): void
    {
        if (!$this->approved()) {
            $this->render('consultancy/pending', 'Consultancy status');
            return;
        }
        $scheduling = new Scheduling();
        $hours = $scheduling->hours();
        $now = $hours->nowUtc();
        $monthStart = $now->setTimezone($hours->timezone())->modify('first day of this month')->format('Y-m-d');
        $id = (int) $this->consultancy['id'];
        $this->render('consultancy/home', 'Consultancy dashboard', [
            'allTime' => $this->service->report($id, $hours->timezone())['totals'],
            'thisMonth' => $this->service->report($id, $hours->timezone(), $monthStart)['totals'],
            'studentCount' => count($this->service->students($id)),
            'upcoming' => $scheduling->presenter()->many($scheduling->repository()->consultancyUpcoming($id, $now->format('Y-m-d H:i:s'), 6)),
        ]);
    }

    public function students(): void
    {
        $this->requireApproved();
        $search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
        $this->render('consultancy/students', 'Students', ['students' => $this->service->students((int) $this->consultancy['id'], $search), 'search' => $search]);
    }

    public function studentForm(): void
    {
        $this->requireApproved();
        $this->renderStudentForm();
    }

    public function addStudent(): void
    {
        $this->requireApproved();
        try {
            $id = $this->service->addStudent($this->consultancy, (int) $this->user['id'], $_POST, $this->file('photo'), $this->file('resume'));
            Session::flash('Student added. You can now book interviews for them.');
            View::redirect('consultancy/student?id=' . $id);
        } catch (ValidationException $error) {
            http_response_code(422);
            $old = array_filter(array_intersect_key($_POST, array_flip(['full_name', 'phone', 'classification', 'experience_years'])), 'is_string');
            if (is_array($_POST['technology_ids'] ?? null)) { $old['technology_ids'] = array_values(array_filter($_POST['technology_ids'], 'is_scalar')); }
            if (is_array($_POST['technology_years'] ?? null)) { $old['technology_years'] = array_filter($_POST['technology_years'], 'is_scalar'); }
            $this->renderStudentForm($error->errors, $old);
        }
    }

    public function student(): void
    {
        $this->requireApproved();
        $student = $this->service->student((int) $this->consultancy['id'], $this->id($_GET));
        if (!$student) { $this->notFound('Student not found', 'This student is not part of your consultancy.'); return; }
        $scheduling = new Scheduling(); $repository = $scheduling->repository(); $presenter = $scheduling->presenter();
        $now = $scheduling->hours()->nowUtc()->format('Y-m-d H:i:s');
        $this->render('consultancy/student', $student['full_name'], ['student' => $student,
            'upcoming' => $presenter->many($repository->candidateUpcoming((int) $student['id'], $now)),
            'past' => $presenter->many($repository->candidatePast((int) $student['id'], $now))]);
    }

    public function bookForm(): void
    {
        $this->requireApproved();
        $studentId = $this->id($_GET, 'student_id');
        $student = $studentId ? $this->service->student((int) $this->consultancy['id'], $studentId) : null;
        if (!$student) {
            $this->render('consultancy/choose-student', 'Book an interview', ['students' => $this->service->students((int) $this->consultancy['id'])]);
            return;
        }
        $this->renderBooking($student);
    }

    public function book(): void
    {
        $this->requireApproved();
        $student = $this->service->student((int) $this->consultancy['id'], $this->id($_POST, 'student_id'));
        if (!$student) { $this->notFound('Student not found', 'This student is not part of your consultancy.'); return; }
        try {
            $id = (new Scheduling())->booking()->create((int) $student['id'], $_POST, (int) $this->user['id']);
            Session::flash('Interview booked for ' . $student['full_name'] . '. The slot is confirmed.');
            View::redirect('consultancy/interview?id=' . $id);
        } catch (ValidationException $error) {
            http_response_code(422);
            $old = array_filter(array_intersect_key($_POST, array_flip(['company_name','job_role','technology_id','interview_round','hr_availability','hr_name','hr_phone','interview_type','support_person_id','duration','date','time'])), 'is_string');
            $this->renderBooking($student, $error->errors, $old);
        }
    }

    public function availability(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (!$this->approved()) { http_response_code(403); echo json_encode(['message' => 'Your consultancy is not approved yet.']); return; }
        $consultancyId = (int) $this->consultancy['id'];
        $exclude = $this->id($_GET, 'interview_id') ?: null;
        $studentId = $exclude ? $this->service->interviewStudent($consultancyId, $exclude) : ($this->service->student($consultancyId, $this->id($_GET, 'student_id'))['id'] ?? null);
        if (!$studentId) { http_response_code(404); echo json_encode(['message' => 'Student or interview not found.']); return; }
        try {
            echo json_encode((new Scheduling())->availability()->availableSlots((int) $studentId, $_GET, $exclude), JSON_THROW_ON_ERROR);
        } catch (ValidationException $error) {
            http_response_code(422);
            echo json_encode(['message' => array_values($error->errors)[0] ?? 'Please check the request.', 'errors' => $error->errors], JSON_THROW_ON_ERROR);
        }
    }

    public function interviews(): void
    {
        $this->requireApproved();
        $scheduling = new Scheduling(); $repository = $scheduling->repository(); $presenter = $scheduling->presenter();
        $id = (int) $this->consultancy['id']; $now = $scheduling->hours()->nowUtc()->format('Y-m-d H:i:s');
        $this->render('candidate/interviews', 'Student interviews', ['area' => 'consultancy',
            'upcoming' => $presenter->many($repository->consultancyUpcoming($id, $now)),
            'past' => $presenter->many($repository->consultancyPast($id, $now)),
            'cancelled' => $presenter->many($repository->consultancyCancelled($id))]);
    }

    public function interview(): void
    {
        $this->requireApproved();
        $scheduling = new Scheduling();
        $interview = $scheduling->repository()->consultancyDetail((int) $this->consultancy['id'], $this->id($_GET));
        if (!$interview) { $this->notFound('Interview not found', 'This interview is unavailable or does not belong to your consultancy.'); return; }
        $this->render('candidate/interview', 'Interview details', ['area' => 'consultancy', 'interview' => $scheduling->presenter()->one($interview)]);
    }

    public function rescheduleForm(): void
    {
        $this->requireApproved();
        $scheduling = new Scheduling();
        $interview = $scheduling->repository()->consultancyDetail((int) $this->consultancy['id'], $this->id($_GET));
        if (!$interview) { $this->notFound('Interview not found', 'This interview is unavailable or does not belong to your consultancy.'); return; }
        $this->renderReschedule($scheduling->presenter()->one($interview));
    }

    public function reschedule(): void
    {
        $this->requireApproved();
        $id = $this->id($_POST);
        $scheduling = new Scheduling();
        $studentId = $this->service->interviewStudent((int) $this->consultancy['id'], $id);
        if (!$studentId) { $this->notFound('Interview not found', 'This interview is unavailable or does not belong to your consultancy.'); return; }
        try {
            $scheduling->booking()->reschedule($studentId, $id, $_POST, (int) $this->user['id']);
            Session::flash('Interview rescheduled. The new time is confirmed.');
            View::redirect('consultancy/interview?id=' . $id);
        } catch (NotFoundException) {
            $this->notFound('Interview not found', 'This interview is unavailable or does not belong to your consultancy.');
        } catch (ValidationException $error) {
            http_response_code(422);
            $interview = $scheduling->repository()->consultancyDetail((int) $this->consultancy['id'], $id);
            $this->renderReschedule($scheduling->presenter()->one($interview), $error->errors, array_filter(array_intersect_key($_POST, array_flip(['date','time','duration','support_person_id'])), 'is_string'));
        }
    }

    public function cancel(): void
    {
        $this->requireApproved();
        $id = $this->id($_POST);
        $studentId = $this->service->interviewStudent((int) $this->consultancy['id'], $id);
        if (!$studentId) { $this->notFound('Interview not found', 'This interview is unavailable or does not belong to your consultancy.'); return; }
        try {
            (new Scheduling())->booking()->cancel($studentId, $id, (int) $this->user['id']);
            Session::flash('Interview cancelled. The slot has been released.');
            View::redirect('consultancy/interviews');
        } catch (NotFoundException) {
            $this->notFound('Interview not found', 'This interview is unavailable or does not belong to your consultancy.');
        } catch (ValidationException $error) {
            Session::flash((string) (array_values($error->errors)[0] ?? "Something went wrong."));
            View::redirect('consultancy/interview?id=' . $id);
        }
    }

    public function reports(): void
    {
        $this->requireApproved();
        $hours = (new Scheduling())->hours();
        $local = $hours->nowUtc()->setTimezone($hours->timezone());
        $from = is_string($_GET['from'] ?? null) && $_GET['from'] !== '' ? $_GET['from'] : $local->modify('first day of this month')->format('Y-m-d');
        $to = is_string($_GET['to'] ?? null) && $_GET['to'] !== '' ? $_GET['to'] : $local->format('Y-m-d');
        $this->render('consultancy/reports', 'Reports', ['from' => $from, 'to' => $to, 'report' => $this->service->report((int) $this->consultancy['id'], $hours->timezone(), $from, $to)]);
    }

    private function renderBooking(array $student, array $errors = [], array $old = []): void
    {
        $db = Database::connection(); $hours = (new Scheduling())->hours();
        $old = array_merge(['technology_id' => '', 'interview_type' => 'NORMAL', 'support_person_id' => '', 'duration' => '30', 'hr_availability' => 'available', 'date' => $hours->nowUtc()->setTimezone($hours->timezone())->modify('+1 day')->format('Y-m-d'), 'time' => ''], $old);
        $repository = new CandidateTechnologyRepository($db);
        $technologies = $repository->directory(true);
        $active = array_fill_keys(array_map('intval', array_column($technologies, 'id')), true);
        $stackIds = array_values(array_filter(array_column($student['tech_stack'], 'id'), static fn($id) => isset($active[$id])));
        $this->render('candidate/book', 'Book an interview', ['area' => 'consultancy', 'student' => $student, 'errors' => $errors, 'old' => $old,
            'minDate' => $hours->nowUtc()->setTimezone($hours->timezone())->format('Y-m-d'), 'technologies' => $technologies, 'candidateTechIds' => $stackIds,
            'supportPeople' => $db->query('SELECT id, name FROM support_people WHERE active = 1 ORDER BY name')->fetchAll(),
            'availabilityQuery' => '?student_id=' . (int) $student['id']]);
    }

    private function renderReschedule(array $interview, array $errors = [], array $old = []): void
    {
        $hours = (new Scheduling())->hours();
        $old = array_merge(['date' => $interview['start_local']->format('Y-m-d'), 'time' => $interview['start_local']->format('H:i'), 'duration' => (string) $interview['duration_minutes'], 'support_person_id' => (string) ($interview['support_person_id'] ?? '')], $old);
        $this->render('candidate/reschedule', 'Reschedule interview', ['area' => 'consultancy', 'interview' => $interview, 'errors' => $errors, 'old' => $old,
            'minDate' => $hours->nowUtc()->setTimezone($hours->timezone())->format('Y-m-d'),
            'supportPeople' => Database::connection()->query('SELECT id, name FROM support_people WHERE active = 1 ORDER BY name')->fetchAll()]);
    }

    private function renderStudentForm(array $errors = [], array $old = []): void
    {
        $this->render('consultancy/student-new', 'Add a student', ['errors' => $errors, 'old' => $old, 'technologies' => (new UserRepository())->technologies()]);
    }

    private function render(string $view, string $title, array $data = []): void
    {
        View::render($view, $data + ['title' => $title, 'user' => $this->user, 'consultancy' => $this->consultancy]);
    }

    private function approved(): bool { return $this->consultancy['status'] === 'APPROVED'; }
    private function requireApproved(): void { if (!$this->approved()) { View::redirect('consultancy'); } }
    private function file(string $key): ?array { return isset($_FILES[$key]) && is_array($_FILES[$key]) ? $_FILES[$key] : null; }
    private function id(array $source, string $key = 'id'): int { $id = filter_var($source[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); return $id ? (int) $id : 0; }
    private function notFound(string $title, string $message): void { http_response_code(404); View::render('error', ['title' => $title, 'message' => $message, 'user' => $this->user]); }
}
