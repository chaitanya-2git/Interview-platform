<?php
declare(strict_types=1);
namespace App\Support;

final class FrozenClock implements Clock
{
    public function __construct(private readonly \DateTimeImmutable $instant) {}

    public function now(\DateTimeZone $timezone): \DateTimeImmutable
    {
        return $this->instant->setTimezone($timezone);
    }
}
