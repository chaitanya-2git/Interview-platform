<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\{Clock, SystemClock};
use App\Validation\ValidationException;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class BusinessHoursService
{
    private const DURATIONS = [30, 45, 60];

    public function __construct(private readonly PDO $db, private readonly Clock $clock = new SystemClock()) {}

    public function timezone(): DateTimeZone
    {
        $query = $this->db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'business_timezone'");
        $query->execute();
        $name = $query->fetchColumn();
        try { return new DateTimeZone(is_string($name) && $name !== '' ? $name : 'Asia/Kolkata'); }
        catch (\Throwable) { throw new \RuntimeException('Business timezone configuration is invalid'); }
    }

    public function advanceMinutes(): int { return $this->positiveSetting('advance_booking_minutes', 60); }
    public function changeCutoffMinutes(): int { return $this->positiveSetting('candidate_change_cutoff_minutes', 60); }
    public function allowedDurations(): array { return self::DURATIONS; }

    public function parseSlot(string $date, string $time, int $duration, bool $enforceAdvance = true): array
    {
        if (!in_array($duration, self::DURATIONS, true)) {
            throw new ValidationException(['duration' => 'Choose a 30, 45, or 60 minute interview.']);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new ValidationException(['date' => 'Choose a valid interview date and time.']);
        }
        $timezone = $this->timezone();
        $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$date $time", $timezone);
        if (!$start || $start->format('Y-m-d H:i') !== "$date $time") {
            throw new ValidationException(['date' => 'Choose a valid interview date and time.']);
        }
        if ((int) $start->format('i') % 15 !== 0) {
            throw new ValidationException(['time' => 'Interview start times must use 15-minute increments.']);
        }
        $hours = $this->hoursFor($start);
        if (!$hours['is_open']) {
            throw new ValidationException(['date' => 'BluJay interview operations are closed on this date.']);
        }
        $open = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $hours['opens_at'], $timezone);
        $close = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $hours['closes_at'], $timezone);
        $end = $start->add(new DateInterval('PT' . $duration . 'M'));
        if ($start < $open) {
            throw new ValidationException(['time' => 'Choose a time within business hours.']);
        }
        if ($end > $close) {
            throw new ValidationException(['time' => 'This interview would finish outside business hours.']);
        }
        if ($enforceAdvance && $start < $this->clock->now($timezone)->add(new DateInterval('PT' . $this->advanceMinutes() . 'M'))) {
            throw new ValidationException(['time' => 'Bookings must be made at least ' . $this->humanMinutes($this->advanceMinutes()) . ' in advance.']);
        }
        $utc = new DateTimeZone('UTC');
        return ['start_local' => $start, 'end_local' => $end, 'start_utc' => $start->setTimezone($utc), 'end_utc' => $end->setTimezone($utc)];
    }

    public function candidateSlotStarts(string $date, int $duration): array
    {
        if (!in_array($duration, self::DURATIONS, true)) {
            throw new ValidationException(['duration' => 'Choose a 30, 45, or 60 minute interview.']);
        }
        $timezone = $this->timezone();
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if (!$day || $day->format('Y-m-d') !== $date) {
            throw new ValidationException(['date' => 'Choose a valid interview date.']);
        }
        $hours = $this->hoursFor($day);
        if (!$hours['is_open']) { return []; }
        $cursor = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $hours['opens_at'], $timezone);
        $close = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $hours['closes_at'], $timezone);
        $earliest = $this->clock->now($timezone)->add(new DateInterval('PT' . $this->advanceMinutes() . 'M'));
        $slots = [];
        while ($cursor->add(new DateInterval('PT' . $duration . 'M')) <= $close) {
            if ($cursor >= $earliest) { $slots[] = $cursor->format('H:i'); }
            $cursor = $cursor->add(new DateInterval('PT15M'));
        }
        return $slots;
    }

    public function canCandidateChange(string $startUtc): bool
    {
        $start = new DateTimeImmutable($startUtc, new DateTimeZone('UTC'));
        $cutoff = $this->clock->now(new DateTimeZone('UTC'))->add(new DateInterval('PT' . $this->changeCutoffMinutes() . 'M'));
        return $start >= $cutoff;
    }

    public function meetsCandidateAdvance(string $startUtc): bool
    {
        $start = new DateTimeImmutable($startUtc, new DateTimeZone('UTC'));
        return $start >= $this->clock->now(new DateTimeZone('UTC'))->add(new DateInterval('PT' . $this->advanceMinutes() . 'M'));
    }

    public function hoursForDate(DateTimeImmutable $day): array { return $this->hoursFor($day); }

    public function nowUtc(): DateTimeImmutable { return $this->clock->now(new DateTimeZone('UTC')); }

    private function hoursFor(DateTimeImmutable $day): array
    {
        $query = $this->db->prepare('SELECT is_open, opens_at, closes_at FROM business_hours WHERE weekday = ?');
        $query->execute([(int) $day->format('N')]);
        $row = $query->fetch();
        if (!$row) { throw new \RuntimeException('Business hours are not configured for this weekday'); }
        return ['is_open' => (bool) $row['is_open'], 'opens_at' => $row['opens_at'], 'closes_at' => $row['closes_at']];
    }

    private function positiveSetting(string $key, int $default): int
    {
        $query = $this->db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $query->execute([$key]);
        $value = filter_var($query->fetchColumn(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10080]]);
        return $value === false ? $default : $value;
    }

    private function humanMinutes(int $minutes): string
    {
        return $minutes === 60 ? '1 hour' : $minutes . ' minutes';
    }
}
