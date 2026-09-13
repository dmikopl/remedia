<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling;

use App\Scheduling\Holiday;
use App\Scheduling\PolishPublicHolidays;
use PHPUnit\Framework\TestCase;

final class PolishPublicHolidaysTest extends TestCase
{
    private PolishPublicHolidays $holidays;

    protected function setUp(): void
    {
        $this->holidays = new PolishPublicHolidays();
    }

    public function testYearHasThirteenPublicHolidays(): void
    {
        self::assertCount(13, $this->holidays->forYear(2026));
    }

    public function testFixedHolidaysArePresent(): void
    {
        $dates = $this->datesFor(2026);

        foreach (['2026-01-01', '2026-05-01', '2026-05-03', '2026-11-11', '2026-12-26'] as $date) {
            self::assertContains($date, $dates);
        }
    }

    public function testMovableFeastsAreDerivedFromEasterSunday(): void
    {
        $dates = $this->datesFor(2026);

        self::assertContains('2026-04-05', $dates);
        self::assertContains('2026-04-06', $dates);
        self::assertContains('2026-05-24', $dates);
        self::assertContains('2026-06-04', $dates);
    }

    public function testEasterMondayMovesBetweenYears(): void
    {
        self::assertContains('2025-04-21', $this->datesFor(2025));
        self::assertContains('2027-03-29', $this->datesFor(2027));
    }

    public function testHolidaysComeBackSortedByDate(): void
    {
        $dates = $this->datesFor(2026);
        $sorted = $dates;
        sort($sorted);

        self::assertSame($sorted, $dates);
    }

    public function testDatesAreAnchoredToMidnightInWarsaw(): void
    {
        foreach ($this->holidays->forYear(2026) as $holiday) {
            self::assertSame('00:00', $holiday->date()->format('H:i'));
            self::assertSame('Europe/Warsaw', $holiday->date()->getTimezone()->getName());
        }
    }

    /**
     * @return list<string>
     */
    private function datesFor(int $year): array
    {
        return array_map(
            static fn (Holiday $holiday): string => $holiday->date()->format('Y-m-d'),
            $this->holidays->forYear($year),
        );
    }
}
