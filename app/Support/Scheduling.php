<?php
declare(strict_types=1);
namespace App\Support;

use App\Repositories\InterviewRepository;
use App\Services\{AuditService, AvailabilityService, BookingService, BusinessHoursService, ResourceAssignmentService};

final class Scheduling
{
    public function __construct(private readonly ?Clock $clock = null) {}

    public function hours(): BusinessHoursService { return new BusinessHoursService(Database::connection(), $this->clock ?? new SystemClock()); }
    public function repository(): InterviewRepository { return new InterviewRepository(Database::connection()); }
    public function presenter(): InterviewPresenter { return new InterviewPresenter($this->hours()); }
    public function availability(): AvailabilityService
    {
        $hours = $this->hours();
        return new AvailabilityService(Database::connection(), $hours, new ResourceAssignmentService(Database::connection()));
    }
    public function booking(): BookingService
    {
        $hours = $this->hours();
        $availability = new AvailabilityService(Database::connection(), $hours, new ResourceAssignmentService(Database::connection()));
        return new BookingService(Database::connection(), $availability, $hours, new AuditService(Database::connection()));
    }
}
