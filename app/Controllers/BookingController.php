<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\CandidateTechnologyRepository;
use App\Services\AuthService;
use App\Support\{Database, NotFoundException, Scheduling, Session, View};
use App\Validation\ValidationException;

final class BookingController
{
    public function form(): void { $this->renderForm(); }

    public function create(): void
    {
        $user = (new AuthService())->user();
        try {
            $id = (new Scheduling())->booking()->create((int) $user['id'], $_POST);
            Session::flash('Interview booked. Your slot is confirmed.');
            View::redirect('candidate/interview?id=' . $id);
        } catch (ValidationException $error) {
            http_response_code(422); $this->renderForm($error->errors, $this->oldBooking());
        }
    }

    public function availability(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $user = (new AuthService())->user();
        try {
            $scheduling = new Scheduling();
            $exclude = filter_var($_GET['interview_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
            if ($exclude && !$scheduling->repository()->candidateDetail((int) $user['id'], (int) $exclude)) {
                http_response_code(404); echo json_encode(['message' => 'Interview not found.'], JSON_THROW_ON_ERROR); return;
            }
            $result = $scheduling->availability()->availableSlots((int) $user['id'], $_GET, $exclude ? (int) $exclude : null);
            echo json_encode($result, JSON_THROW_ON_ERROR);
        } catch (ValidationException $error) {
            http_response_code(422);
            echo json_encode(['message' => reset($error->errors), 'errors' => $error->errors], JSON_THROW_ON_ERROR);
        }
    }

    public function index(): void
    {
        $user = (new AuthService())->user(); $scheduling = new Scheduling(); $hours = $scheduling->hours(); $repository = $scheduling->repository(); $presenter = $scheduling->presenter();
        $now = $hours->nowUtc()->format('Y-m-d H:i:s');
        View::render('candidate/interviews', ['title' => 'My interviews', 'user' => $user,
            'upcoming' => $presenter->many($repository->candidateUpcoming((int) $user['id'], $now)),
            'past' => $presenter->many($repository->candidatePast((int) $user['id'], $now)),
            'cancelled' => $presenter->many($repository->candidateCancelled((int) $user['id']))]);
    }

    public function detail(): void
    {
        $user = (new AuthService())->user(); $id = $this->id($_GET);
        if (!$id) { $this->notFound($user); return; }
        $scheduling = new Scheduling(); $interview = $scheduling->repository()->candidateDetail((int) $user['id'], $id);
        if (!$interview) { $this->notFound($user); return; }
        View::render('candidate/interview', ['title' => 'Interview details', 'user' => $user, 'interview' => $scheduling->presenter()->one($interview)]);
    }

    public function rescheduleForm(): void
    {
        $user = (new AuthService())->user(); $id = $this->id($_GET); $scheduling = new Scheduling();
        if (!$id) { $this->notFound($user); return; }
        $interview = $scheduling->repository()->candidateDetail((int) $user['id'], $id);
        if (!$interview) { $this->notFound($user); return; }
        $this->renderReschedule($user, $scheduling->presenter()->one($interview));
    }

    public function reschedule(): void
    {
        $user = (new AuthService())->user(); $id = $this->id($_POST); $scheduling = new Scheduling();
        if (!$id) { $this->notFound($user); return; }
        try {
            $scheduling->booking()->reschedule((int) $user['id'], $id, $_POST);
            Session::flash('Interview rescheduled. Your new time is confirmed.');
            View::redirect('candidate/interview?id=' . $id);
        } catch (NotFoundException) { $this->notFound($user); }
        catch (ValidationException $error) {
            $interview = $scheduling->repository()->candidateDetail((int) $user['id'], $id);
            if (!$interview) { $this->notFound($user); return; }
            http_response_code(422); $this->renderReschedule($user, $scheduling->presenter()->one($interview), $error->errors, $this->oldSchedule());
        }
    }

    public function cancel(): void
    {
        $user = (new AuthService())->user(); $id = $this->id($_POST);
        if (!$id) { $this->notFound($user); return; }
        try {
            (new Scheduling())->booking()->cancel((int) $user['id'], $id);
            Session::flash('Interview cancelled. The slot has been released.');
            View::redirect('candidate/interviews');
        } catch (NotFoundException) { $this->notFound($user); }
        catch (ValidationException $error) { Session::flash(reset($error->errors)); View::redirect('candidate/interview?id=' . $id); }
    }

    private function renderForm(array $errors = [], array $old = []): void
    {
        $user = (new AuthService())->user(); $db = Database::connection(); $scheduling = new Scheduling();
        $hours = $scheduling->hours();
        $old = array_merge(['technology_id' => '', 'interview_type' => 'NORMAL', 'support_person_id' => '', 'duration' => '30', 'hr_availability' => 'available', 'date' => $hours->nowUtc()->setTimezone($hours->timezone())->modify('+1 day')->format('Y-m-d'), 'time' => ''], $old);
        $technologyRepo=new CandidateTechnologyRepository($db);$technologies=$technologyRepo->directory(true);$activeIds=array_fill_keys(array_map('intval',array_column($technologies,'id')),true);$candidateTechIds=array_values(array_filter(array_column($technologyRepo->stack((int)$user['id']),'id'),static fn($id)=>isset($activeIds[$id])));
        View::render('candidate/book', ['title' => 'Book an interview', 'user' => $user, 'errors' => $errors, 'old' => $old,
            'minDate' => $hours->nowUtc()->setTimezone($hours->timezone())->format('Y-m-d'),
            'technologies' => $technologies,'candidateTechIds'=>$candidateTechIds,
            'supportPeople' => $db->query('SELECT id, name FROM support_people WHERE active = 1 ORDER BY name')->fetchAll()]);
    }

    private function renderReschedule(array $user, array $interview, array $errors = [], array $old = []): void
    {
        $old = array_merge(['date' => $interview['start_local']->format('Y-m-d'), 'time' => $interview['start_local']->format('H:i'), 'duration' => (string) $interview['duration_minutes'], 'support_person_id' => (string) ($interview['support_person_id'] ?? '')], $old);
        $hours = (new Scheduling())->hours();
        View::render('candidate/reschedule', ['title' => 'Reschedule interview', 'user' => $user, 'interview' => $interview, 'errors' => $errors, 'old' => $old,
            'minDate' => $hours->nowUtc()->setTimezone($hours->timezone())->format('Y-m-d'),
            'supportPeople' => Database::connection()->query('SELECT id, name FROM support_people WHERE active = 1 ORDER BY name')->fetchAll()]);
    }

    private function oldBooking(): array
    {
        $keys = ['company_name','job_role','technology_id','interview_round','hr_availability','hr_name','hr_phone','interview_type','support_person_id','duration','date','time'];
        return array_filter(array_intersect_key($_POST, array_flip($keys)), 'is_string');
    }
    private function oldSchedule(): array { return array_filter(array_intersect_key($_POST, array_flip(['date','time','duration','support_person_id'])), 'is_string'); }
    private function id(array $source): int { $id = filter_var($source['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); return $id ? (int) $id : 0; }
    private function notFound(array $user): void { http_response_code(404); View::render('error', ['title' => 'Interview not found', 'message' => 'This interview is unavailable or does not belong to your account.', 'user' => $user]); }
}
