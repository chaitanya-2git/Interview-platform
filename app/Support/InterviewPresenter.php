<?php
declare(strict_types=1);
namespace App\Support;

use App\Services\BusinessHoursService;
use DateTimeImmutable;
use DateTimeZone;

final class InterviewPresenter
{
    public function __construct(private readonly BusinessHoursService $hours) {}

    public function one(array $row): array
    {
        $timezone = $this->hours->timezone();
        $start = (new DateTimeImmutable($row['start_time'], new DateTimeZone('UTC')))->setTimezone($timezone);
        $end = (new DateTimeImmutable($row['end_time'], new DateTimeZone('UTC')))->setTimezone($timezone);
        return $row + [
            'start_local' => $start,
            'end_local' => $end,
            'display_date' => $start->format('D, d M Y'),
            'display_time' => $start->format('g:i A') . ' – ' . $end->format('g:i A'),
            'date_value' => $start->format('Y-m-d'),
            'time_value' => $start->format('H:i'),
            'can_change' => $row['status'] === 'BOOKED' && $this->hours->canCandidateChange($row['start_time']),
        ];
    }

    public function many(array $rows): array { return array_map($this->one(...), $rows); }
}
