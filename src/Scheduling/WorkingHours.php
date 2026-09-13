<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateTimeImmutable;
use LogicException;

final class WorkingHours
{
    /**
     * @param array<string, array{open: string, close: string}> $schedule
     */
    public function __construct(private readonly array $schedule)
    {
    }

    public function isOpenOn(DateTimeImmutable $day): bool
    {
        return isset($this->schedule[$this->weekdayOf($day)]);
    }

    public function opensAt(DateTimeImmutable $day): DateTimeImmutable
    {
        return $this->localTime($day, 'open');
    }

    public function closesAt(DateTimeImmutable $day): DateTimeImmutable
    {
        return $this->localTime($day, 'close');
    }

    /**
     * @param 'open'|'close' $edge
     */
    private function localTime(DateTimeImmutable $day, string $edge): DateTimeImmutable
    {
        $weekday = $this->weekdayOf($day);

        if (!isset($this->schedule[$weekday])) {
            throw new LogicException(sprintf('No working hours are defined for %s.', $weekday));
        }

        return new DateTimeImmutable(
            $day->format('Y-m-d') . ' ' . $this->schedule[$weekday][$edge],
            BookingTimezone::get(),
        );
    }

    private function weekdayOf(DateTimeImmutable $day): string
    {
        return strtolower($day->format('l'));
    }
}
