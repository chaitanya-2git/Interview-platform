<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\NotFoundException;
use App\Validation\{Input, ValidationException};
use PDO;

final class BookingService
{
    public function __construct(
        private readonly PDO $db,
        private readonly AvailabilityService $availability,
        private readonly BusinessHoursService $hours,
        private readonly AuditService $audit,
    ) {}

    public function create(int $candidateId, array $input): int
    {
        $details = $this->validateDetails($input);
        return $this->transaction(function () use ($candidateId, $input, $details): int {
            $this->lockScheduling();
            $candidate = $this->candidateSnapshot($candidateId);
            $this->activeTechnology($details['technology_id'], true);
            $slot = $this->availability->evaluate($candidateId, $input, null, true);
            $query = $this->db->prepare("INSERT INTO interviews
                (candidate_id, candidate_reference_id, candidate_name_snapshot, candidate_classification_snapshot, resource_id, support_person_id, technology_id, company_name, job_role, interview_round, hr_name, hr_phone, hr_unavailable, duration_minutes, interview_type, start_time, end_time, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'BOOKED')");
            $query->execute([
                $candidateId, $candidateId, $candidate['full_name'], $candidate['classification'], $slot['resource_id'], $slot['support_person_id'], $details['technology_id'],
                $details['company_name'], $details['job_role'], $details['interview_round'], $details['hr_name'], $details['hr_phone'], $details['hr_unavailable'],
                $slot['duration'], $slot['interview_type'], $slot['start_utc']->format('Y-m-d H:i:s'), $slot['end_utc']->format('Y-m-d H:i:s'),
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->audit->record($candidateId, 'booking.created', 'interview', $id, $this->auditSlot($slot));
            return $id;
        });
    }

    public function createAsAdmin(int $actorId, int $candidateId, array $input): int
    {
        $details = $this->validateDetails($input);
        return $this->transaction(function () use ($actorId, $candidateId, $input, $details): int {
            $this->lockScheduling();
            $candidate = $this->candidateSnapshot($candidateId);
            $this->activeTechnology($details['technology_id'], true);
            $slot = $this->availability->evaluate($candidateId, $input, null, true, false);
            $query = $this->db->prepare("INSERT INTO interviews
                (candidate_id, candidate_reference_id, candidate_name_snapshot, candidate_classification_snapshot, resource_id, support_person_id, technology_id, company_name, job_role, interview_round, hr_name, hr_phone, hr_unavailable, duration_minutes, interview_type, start_time, end_time, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'BOOKED')");
            $query->execute([$candidateId, $candidateId, $candidate['full_name'], $candidate['classification'], $slot['resource_id'], $slot['support_person_id'], $details['technology_id'], $details['company_name'], $details['job_role'], $details['interview_round'], $details['hr_name'], $details['hr_phone'], $details['hr_unavailable'], $slot['duration'], $slot['interview_type'], $slot['start_utc']->format('Y-m-d H:i:s'), $slot['end_utc']->format('Y-m-d H:i:s')]);
            $id = (int) $this->db->lastInsertId();
            $metadata = $this->auditSlot($slot) + ['candidate_id' => $candidateId, 'time_cutoff_bypassed' => !$this->hours->meetsCandidateAdvance($slot['start_utc']->format('Y-m-d H:i:s'))];
            $this->audit->record($actorId, 'admin.booking.created', 'interview', $id, $metadata);
            if ($metadata['time_cutoff_bypassed']) { $this->audit->record($actorId, 'admin.cutoff_override', 'interview', $id, ['operation' => 'create']); }
            return $id;
        });
    }

    public function cancel(int $candidateId, int $interviewId): void
    {
        $this->transaction(function () use ($candidateId, $interviewId): void {
            $this->lockScheduling();
            $interview = $this->ownedBooking($candidateId, $interviewId);
            if ($interview['status'] !== 'BOOKED') { throw new ValidationException(['form' => 'This interview is already cancelled.']); }
            if (!$this->hours->canCandidateChange($interview['start_time'])) {
                throw new ValidationException(['form' => 'This interview can no longer be cancelled because it is inside the change cutoff.']);
            }
            $query = $this->db->prepare("UPDATE interviews SET status = 'CANCELLED' WHERE id = ? AND candidate_id = ? AND status = 'BOOKED'");
            $query->execute([$interviewId, $candidateId]);
            if ($query->rowCount() !== 1) { throw new ValidationException(['form' => 'This interview could not be cancelled. Refresh and try again.']); }
            $this->audit->record($candidateId, 'booking.cancelled', 'interview', $interviewId, ['start_time_utc' => $interview['start_time'], 'resource_id' => (int) $interview['resource_id']]);
        });
    }

    public function reschedule(int $candidateId, int $interviewId, array $input): void
    {
        $this->transaction(function () use ($candidateId, $interviewId, $input): void {
            $this->lockScheduling();
            $interview = $this->ownedBooking($candidateId, $interviewId);
            if ($interview['status'] !== 'BOOKED') { throw new ValidationException(['form' => 'Cancelled interviews cannot be rescheduled.']); }
            if (!$this->hours->canCandidateChange($interview['start_time'])) {
                throw new ValidationException(['form' => 'This interview can no longer be rescheduled because it is inside the change cutoff.']);
            }
            // The booking type is immutable during candidate rescheduling; never trust a posted type.
            $request = array_merge($input, ['interview_type' => $interview['interview_type']]);
            $slot = $this->availability->evaluate($candidateId, $request, $interviewId, true);
            $before = ['start_time_utc' => $interview['start_time'], 'end_time_utc' => $interview['end_time'], 'duration_minutes' => (int) $interview['duration_minutes'], 'resource_id' => (int) $interview['resource_id'], 'support_person_id' => $interview['support_person_id'] ? (int) $interview['support_person_id'] : null];
            $query = $this->db->prepare('UPDATE interviews SET resource_id = ?, support_person_id = ?, duration_minutes = ?, start_time = ?, end_time = ? WHERE id = ? AND candidate_id = ? AND status = \'BOOKED\'');
            $query->execute([$slot['resource_id'], $slot['support_person_id'], $slot['duration'], $slot['start_utc']->format('Y-m-d H:i:s'), $slot['end_utc']->format('Y-m-d H:i:s'), $interviewId, $candidateId]);
            if ($query->rowCount() !== 1) { throw new ValidationException(['form' => 'The interview schedule did not change. Choose a different slot.']); }
            $this->audit->record($candidateId, 'booking.rescheduled', 'interview', $interviewId, ['before' => $before, 'after' => $this->auditSlot($slot)]);
        });
    }

    public function cancelAsAdmin(int $actorId, int $interviewId): void
    {
        $this->transaction(function () use ($actorId, $interviewId): void {
            $this->lockScheduling();
            $interview = $this->adminBooking($interviewId);
            if ($interview['status'] !== 'BOOKED') { throw new ValidationException(['form' => 'This interview is already cancelled.']); }
            $query = $this->db->prepare("UPDATE interviews SET status = 'CANCELLED' WHERE id = ? AND status = 'BOOKED'");
            $query->execute([$interviewId]);
            if ($query->rowCount() !== 1) { throw new ValidationException(['form' => 'This interview could not be cancelled. Refresh and try again.']); }
            $override = !$this->hours->canCandidateChange($interview['start_time']);
            $this->audit->record($actorId, 'admin.booking.cancelled', 'interview', $interviewId, ['start_time_utc' => $interview['start_time'], 'resource_id' => (int) $interview['resource_id'], 'time_cutoff_bypassed' => $override]);
            if ($override) { $this->audit->record($actorId, 'admin.cutoff_override', 'interview', $interviewId, ['operation' => 'cancel']); }
        });
    }

    public function rescheduleAsAdmin(int $actorId, int $interviewId, array $input): void
    {
        $details = $this->validateDetails($input);
        $this->transaction(function () use ($actorId, $interviewId, $input, $details): void {
            $this->lockScheduling();
            $interview = $this->adminBooking($interviewId);
            if ($interview['status'] !== 'BOOKED') { throw new ValidationException(['form' => 'Cancelled interviews cannot be rescheduled.']); }
            if ($interview['candidate_id'] === null) { throw new ValidationException(['form' => 'Deleted-candidate interviews cannot be rescheduled. Cancel this booking and create a new one if the schedule must change.']); }
            $this->activeTechnology($details['technology_id'], true);
            $slot = $this->availability->evaluate((int) $interview['candidate_id'], $input, $interviewId, true, false);
            $before = ['start_time_utc' => $interview['start_time'], 'end_time_utc' => $interview['end_time'], 'duration_minutes' => (int) $interview['duration_minutes'], 'interview_type' => $interview['interview_type'], 'resource_id' => (int) $interview['resource_id'], 'support_person_id' => $interview['support_person_id'] ? (int) $interview['support_person_id'] : null];
            $query = $this->db->prepare("UPDATE interviews SET resource_id=?, support_person_id=?, technology_id=?, company_name=?, job_role=?, interview_round=?, hr_name=?, hr_phone=?, hr_unavailable=?, duration_minutes=?, interview_type=?, start_time=?, end_time=? WHERE id=? AND status='BOOKED'");
            $query->execute([$slot['resource_id'], $slot['support_person_id'], $details['technology_id'], $details['company_name'], $details['job_role'], $details['interview_round'], $details['hr_name'], $details['hr_phone'], $details['hr_unavailable'], $slot['duration'], $slot['interview_type'], $slot['start_utc']->format('Y-m-d H:i:s'), $slot['end_utc']->format('Y-m-d H:i:s'), $interviewId]);
            $override = !$this->hours->canCandidateChange($interview['start_time']);
            $this->audit->record($actorId, 'admin.booking.rescheduled', 'interview', $interviewId, ['before' => $before, 'after' => $this->auditSlot($slot), 'time_cutoff_bypassed' => $override]);
            if ($override) { $this->audit->record($actorId, 'admin.cutoff_override', 'interview', $interviewId, ['operation' => 'reschedule']); }
        });
    }

    private function validateDetails(array $input): array
    {
        $company = Input::text($input, 'company_name');
        $role = Input::text($input, 'job_role');
        $round = Input::text($input, 'interview_round');
        $technology = filter_var($input['technology_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $hrAvailability = Input::text($input, 'hr_availability');
        $errors = [];
        if (!$this->validText($company, 2, 190)) { $errors['company_name'] = 'Enter the company name (2–190 characters).'; }
        if (!$this->validText($role, 2, 190)) { $errors['job_role'] = 'Enter the job role (2–190 characters).'; }
        if (!$this->validText($round, 1, 100)) { $errors['interview_round'] = 'Enter the interview round (up to 100 characters).'; }
        if (!$technology) { $errors['technology_id'] = 'Choose an active technology.'; }
        if (!in_array($hrAvailability, ['available', 'unavailable'], true)) { $errors['hr_availability'] = 'Tell us whether HR details are available.'; }
        $hrName = null; $hrPhone = null;
        if ($hrAvailability === 'available') {
            $hrName = Input::text($input, 'hr_name');
            $hrPhone = Input::phone(Input::text($input, 'hr_phone'));
            if (!$this->validText($hrName, 2, 120)) { $errors['hr_name'] = 'Enter the HR contact name.'; }
            if ($hrPhone === null) { $errors['hr_phone'] = 'Enter a valid HR phone number.'; }
        }
        if ($errors) { throw new ValidationException($errors); }
        return ['company_name' => $company, 'job_role' => $role, 'interview_round' => $round, 'technology_id' => (int) $technology, 'hr_name' => $hrName, 'hr_phone' => $hrPhone, 'hr_unavailable' => $hrAvailability === 'unavailable' ? 1 : 0];
    }

    private function activeTechnology(int $id, bool $locking): void
    {
        $query = $this->db->prepare('SELECT id FROM technologies WHERE id = ? AND active = 1' . ($locking ? ' FOR UPDATE' : ''));
        $query->execute([$id]);
        if (!$query->fetchColumn()) { throw new ValidationException(['technology_id' => 'This technology is no longer available. Choose another.']); }
    }

    private function candidateSnapshot(int $candidateId): array
    {
        $query=$this->db->prepare("SELECT u.full_name,p.classification FROM users u JOIN candidate_profiles p ON p.user_id=u.id WHERE u.id=? AND u.role='CANDIDATE' AND u.active=1 FOR UPDATE");
        $query->execute([$candidateId]);$candidate=$query->fetch();
        if(!$candidate){throw new ValidationException(['form'=>'This candidate account is not available for booking.']);}
        return $candidate;
    }

    private function ownedBooking(int $candidateId, int $interviewId): array
    {
        $query = $this->db->prepare('SELECT * FROM interviews WHERE id = ? AND candidate_id = ? FOR UPDATE');
        $query->execute([$interviewId, $candidateId]);
        $interview = $query->fetch();
        if (!$interview) { throw new NotFoundException('Interview not found'); }
        return $interview;
    }

    private function adminBooking(int $interviewId): array
    {
        $query = $this->db->prepare('SELECT * FROM interviews WHERE id = ? FOR UPDATE');
        $query->execute([$interviewId]);
        $interview = $query->fetch();
        if (!$interview) { throw new NotFoundException('Interview not found'); }
        return $interview;
    }

    private function lockScheduling(): void
    {
        $row = $this->db->query('SELECT id FROM scheduling_mutex WHERE id = 1 FOR UPDATE')->fetchColumn();
        if (!$row) { throw new \RuntimeException('Scheduling mutex is not configured'); }
    }

    private function transaction(callable $callback): mixed
    {
        $this->db->beginTransaction();
        try { $result = $callback(); $this->db->commit(); return $result; }
        catch (\Throwable $error) { if ($this->db->inTransaction()) { $this->db->rollBack(); } throw $error; }
    }

    private function validText(string $value, int $min, int $max): bool
    {
        return mb_strlen($value) >= $min && mb_strlen($value) <= $max && !preg_match('/[\x00-\x1f]/', $value);
    }

    private function auditSlot(array $slot): array
    {
        return ['start_time_utc' => $slot['start_utc']->format('Y-m-d H:i:s'), 'end_time_utc' => $slot['end_utc']->format('Y-m-d H:i:s'), 'duration_minutes' => $slot['duration'], 'interview_type' => $slot['interview_type'], 'resource_id' => $slot['resource_id'], 'support_person_id' => $slot['support_person_id']];
    }
}
