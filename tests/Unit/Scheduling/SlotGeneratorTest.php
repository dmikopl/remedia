<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling;

use App\Scheduling\BookingTimezone;
use App\Scheduling\Slot;
use App\Scheduling\SlotGenerator;
use App\Scheduling\WorkingHours;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class SlotGeneratorTest extends TestCase
{
    private const SCHEDULE = [
        'monday' => ['open' => '09:00', 'close' => '17:00'],
        'tuesday' => ['open' => '09:00', 'close' => '17:00'],
        'wednesday' => ['open' => '09:00', 'close' => '17:00'],
        'thursday' => ['open' => '09:00', 'close' => '17:00'],
        'friday' => ['open' => '09:00', 'close' => '17:00'],
        'saturday' => ['open' => '10:00', 'close' => '14:20'],
    ];

    private SlotGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SlotGenerator(new WorkingHours(self::SCHEDULE), new InMemoryHolidayCalendar(), 30);
    }

    public function testWeekdayIsSplitIntoSixteenSlots(): void
    {
        $slots = $this->generator->generateFor($this->day('2026-09-14'));

        self::assertCount(16, $slots);
        self::assertSame('09:00', $this->startOf($slots[0]));
        self::assertSame('16:30', $this->startOf($slots[15]));
        self::assertSame('17:00', $slots[15]->end()->format('H:i'));
    }

    public function testFridayFollowsTheSameScheduleAsMonday(): void
    {
        $friday = $this->generator->generateFor($this->day('2026-09-18'));
        $monday = $this->generator->generateFor($this->day('2026-09-14'));

        self::assertSame(
            array_map($this->startOf(...), $monday),
            array_map($this->startOf(...), $friday),
        );
    }

    public function testSaturdayDropsTheSlotThatWouldCrossClosingTime(): void
    {
        $slots = $this->generator->generateFor($this->day('2026-09-19'));

        self::assertCount(8, $slots);
        self::assertSame('10:00', $this->startOf($slots[0]));
        self::assertSame('13:30', $this->startOf($slots[7]));
        self::assertSame('14:00', $slots[7]->end()->format('H:i'));
    }

    public function testSundayIsClosed(): void
    {
        self::assertSame([], $this->generator->generateFor($this->day('2026-09-20')));
    }

    public function testHolidayFallingOnAWorkingDayHasNoSlots(): void
    {
        $generator = new SlotGenerator(
            new WorkingHours(self::SCHEDULE),
            new InMemoryHolidayCalendar(['2026-11-11']),
            30,
        );

        self::assertNotSame([], $generator->generateFor($this->day('2026-11-10')));
        self::assertSame([], $generator->generateFor($this->day('2026-11-11')));
    }

    public function testSlotsNeverRunPastClosingTime(): void
    {
        $workingHours = new WorkingHours(self::SCHEDULE);

        foreach (['2026-09-14', '2026-09-19'] as $date) {
            $day = $this->day($date);
            $closesAt = $workingHours->closesAt($day);

            foreach ($this->generator->generateFor($day) as $slot) {
                self::assertLessThanOrEqual($closesAt, $slot->end());
            }
        }
    }

    public function testSlotsStayAnchoredToWarsawTimeAcrossDstChange(): void
    {
        $summer = $this->generator->generateFor($this->day('2026-07-15'));
        $winter = $this->generator->generateFor($this->day('2026-12-16'));

        self::assertSame('07:00', $summer[0]->start()->setTimezone(new DateTimeZone('UTC'))->format('H:i'));
        self::assertSame('08:00', $winter[0]->start()->setTimezone(new DateTimeZone('UTC'))->format('H:i'));
    }

    private function day(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, BookingTimezone::get());
    }

    private function startOf(Slot $slot): string
    {
        return $slot->start()->format('H:i');
    }
}
