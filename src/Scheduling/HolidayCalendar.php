<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateTimeImmutable;

interface HolidayCalendar
{
    public function isHoliday(DateTimeImmutable $day): bool;
}
