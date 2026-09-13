<?php

declare(strict_types=1);

namespace App\Tests\Integration\Reservation;

use App\Reservation\Reservation;
use App\Reservation\ReservationService;
use App\Reservation\SlotAvailability;
use App\Scheduling\BookingTimezone;
use App\Scheduling\Slot;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SlotAvailabilityTest extends KernelTestCase
{
    private const MONDAY = '2026-09-14';

    private SlotAvailability $availability;
    private ReservationService $reservations;
    private Connection $connection;
    private DebugDataHolder $queries;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        /** @var SlotAvailability $availability */
        $availability = $container->get(SlotAvailability::class);
        /** @var ReservationService $reservations */
        $reservations = $container->get(ReservationService::class);
        /** @var Connection $connection */
        $connection = $container->get('doctrine.dbal.default_connection');
        /** @var DebugDataHolder $queries */
        $queries = $container->get('doctrine.debug_data_holder');

        $this->availability = $availability;
        $this->reservations = $reservations;
        $this->connection = $connection;
        $this->queries = $queries;

        $this->connection->executeStatement('DELETE FROM reservation');
        $this->connection->executeStatement('DELETE FROM holiday');
    }

    public function testEmptyWorkingDayOffersEverySlot(): void
    {
        self::assertCount(16, $this->availability->availableOn($this->day(self::MONDAY)));
    }

    public function testBookedSlotDropsOutOfAvailability(): void
    {
        $this->book('09:00');

        $available = $this->startTimes();

        self::assertCount(15, $available);
        self::assertNotContains('09:00', $available);
        self::assertContains('09:30', $available);
    }

    public function testCancelledReservationReturnsTheSlot(): void
    {
        $reservation = $this->book('09:00');
        $this->reservations->cancel($reservation->id());

        self::assertContains('09:00', $this->startTimes());
    }

    public function testClosedDayOffersNothing(): void
    {
        self::assertSame([], $this->availability->availableOn($this->day('2026-09-20')));
    }

    public function testHolidayOffersNothing(): void
    {
        $this->connection->insert('holiday', ['date' => '2026-11-11', 'name' => 'Swieto Niepodleglosci']);

        self::assertSame([], $this->availability->availableOn($this->day('2026-11-11')));
    }

    public function testQueryCountDoesNotGrowWithTheNumberOfReservations(): void
    {
        $this->book('09:00');
        $withOneReservation = $this->queriesSpentOnAvailability();

        foreach (['09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30'] as $time) {
            $this->book($time);
        }

        $withTenReservations = $this->queriesSpentOnAvailability();

        self::assertSame(2, $withOneReservation);
        self::assertSame($withOneReservation, $withTenReservations);
    }

    private function queriesSpentOnAvailability(): int
    {
        $this->queries->reset();
        $this->availability->availableOn($this->day(self::MONDAY));

        return count($this->queries->getData()['default'] ?? []);
    }

    /**
     * @return list<string>
     */
    private function startTimes(): array
    {
        return array_map(
            static fn (Slot $slot): string => $slot->start()->format('H:i'),
            $this->availability->availableOn($this->day(self::MONDAY)),
        );
    }

    private function book(string $localTime): Reservation
    {
        return $this->reservations->book(
            new DateTimeImmutable(self::MONDAY . ' ' . $localTime, BookingTimezone::get()),
            'Anna Kowalska',
            'anna.kowalska@example.com',
        );
    }

    private function day(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, BookingTimezone::get());
    }
}
