<?php
declare(strict_types=1);
namespace App\Services;

use App\Validation\{Input, ValidationException};
use PDO;

final class AvailabilityService
{
    public function __construct(
        private readonly PDO $db,
        private readonly BusinessHoursService $hours,
        private readonly ResourceAssignmentService $resources,
    ) {}

    public function availableSlots(int $candidateId, array $input, ?int $excludeInterviewId = null): array
    {
        [$type, $duration, $supportId] = $this->normalizeSetup($input);
        $this->assertCandidate($candidateId, false);
        $this->supportPerson($type, $supportId, false);
        $date = Input::text($input, 'date');
        $available = [];
        foreach ($this->hours->candidateSlotStarts($date, $duration) as $time) {
            try {
                $result = $this->evaluate($candidateId, ['date' => $date, 'time' => $time, 'duration' => (string) $duration, 'interview_type' => $type, 'support_person_id' => $supportId ? (string) $supportId : ''], $excludeInterviewId);
                $available[] = ['start' => $result['start_local']->format('H:i'), 'end' => $result['end_local']->format('H:i'), 'label' => $result['start_local']->format('g:i A') . ' – ' . $result['end_local']->format('g:i A')];
            } catch (ValidationException $error) {
                if (isset($error->errors['time'])) { continue; }
                throw $error;
            }
        }
        return ['date' => $date, 'duration_minutes' => $duration, 'interview_type' => $type, 'slots' => $available];
    }

    public function evaluate(int $candidateId, array $input, ?int $excludeInterviewId = null, bool $locking = false, bool $enforceAdvance = true): array
    {
        [$type, $duration, $supportId] = $this->normalizeSetup($input);
        $this->assertCandidate($candidateId, $locking);
        $support = $this->supportPerson($type, $supportId, $locking);
        $slot = $this->hours->parseSlot(Input::text($input, 'date'), Input::text($input, 'time'), $duration, $enforceAdvance);
        $exclude = $excludeInterviewId ?? 0;
        if ($this->hasConflict('candidate_id', $candidateId, $slot, $exclude)) {
            throw new ValidationException(['time' => 'You already have another interview during this time.']);
        }
        if ($support && $this->hasConflict('support_person_id', (int) $support['id'], $slot, $exclude)) {
            throw new ValidationException(['time' => $support['name'] . ' is already assigned to another interview during this time.']);
        }
        $resource = $this->resources->choose($type, $slot, $excludeInterviewId, $locking);
        if (!$resource) {
            throw new ValidationException(['time' => 'No ' . ($type === 'TOOL' ? 'Tool' : 'Normal') . ' computer is available for the selected time.']);
        }
        return $slot + ['duration' => $duration, 'interview_type' => $type, 'support_person_id' => $supportId, 'support_person_name' => $support['name'] ?? null, 'resource_id' => (int) $resource['id'], 'resource_name' => $resource['name']];
    }

    private function normalizeSetup(array $input): array
    {
        $type = strtoupper(Input::text($input, 'interview_type'));
        if (!in_array($type, ['TOOL', 'NORMAL'], true)) { throw new ValidationException(['interview_type' => 'Choose Tool or Normal interview support.']); }
        $duration = filter_var($input['duration'] ?? null, FILTER_VALIDATE_INT);
        if ($duration === false || !in_array($duration, $this->hours->allowedDurations(), true)) { throw new ValidationException(['duration' => 'Choose a 30, 45, or 60 minute interview.']); }
        $rawSupport = Input::text($input, 'support_person_id');
        $supportId = $rawSupport === '' ? null : filter_var($rawSupport, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($type === 'TOOL' && !$supportId) { throw new ValidationException(['support_person_id' => 'Choose an active support person for a Tool interview.']); }
        if ($type === 'NORMAL' && $rawSupport !== '') { throw new ValidationException(['support_person_id' => 'Normal interviews do not use a support person.']); }
        return [$type, (int) $duration, $type === 'TOOL' ? (int) $supportId : null];
    }

    private function assertCandidate(int $candidateId, bool $locking): void
    {
        $query = $this->db->prepare("SELECT p.user_id FROM candidate_profiles p JOIN users u ON u.id = p.user_id WHERE p.user_id = ? AND u.role = 'CANDIDATE' AND u.active = 1" . ($locking ? ' FOR UPDATE' : ''));
        $query->execute([$candidateId]);
        if (!$query->fetchColumn()) { throw new ValidationException(['form' => 'Your candidate account is not available for booking.']); }
    }

    private function supportPerson(string $type, ?int $supportId, bool $locking): ?array
    {
        if ($type === 'NORMAL') { return null; }
        $query = $this->db->prepare('SELECT id, name FROM support_people WHERE id = ? AND active = 1' . ($locking ? ' FOR UPDATE' : ''));
        $query->execute([$supportId]);
        $person = $query->fetch();
        if (!$person) { throw new ValidationException(['support_person_id' => 'This support person is no longer available. Choose another.']); }
        return $person;
    }

    private function hasConflict(string $column, int $id, array $slot, int $exclude): bool
    {
        if (!in_array($column, ['candidate_id', 'support_person_id'], true)) { throw new \LogicException('Invalid conflict column'); }
        $query = $this->db->prepare("SELECT 1 FROM interviews WHERE $column = ? AND status = 'BOOKED' AND start_time < ? AND end_time > ? AND (? = 0 OR id <> ?) LIMIT 1");
        $query->execute([$id, $slot['end_utc']->format('Y-m-d H:i:s'), $slot['start_utc']->format('Y-m-d H:i:s'), $exclude, $exclude]);
        return (bool) $query->fetchColumn();
    }
}
