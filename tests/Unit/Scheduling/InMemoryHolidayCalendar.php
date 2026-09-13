<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling;

use App\Scheduling\HolidayCalendar;
use DateTimeImmutable;

final class InMemoryHolidayCalendar implements HolidayCalendar
{
    /**
     * @param list<string> $dates
     */
    public function __construct(private readonly array $dates = [])
    {
    }

    public function isHoliday(DateTimeImmutable $day): bool
    {
        return in_array($day->format('Y-m-d'), $this->dates, true);
    }
}
