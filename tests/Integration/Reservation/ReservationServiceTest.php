<?php

declare(strict_types=1);

namespace App\Tests\Integration\Reservation;

use App\Reservation\Exception\ReservationAlreadyCancelled;
use App\Reservation\Exception\ReservationNotFound;
use App\Reservation\Exception\SlotAlreadyBooked;
use App\Reservation\Exception\SlotNotBookable;
use App\Reservation\Reservation;
use App\Reservation\ReservationService;
use App\Reservation\ReservationStatus;
use App\Scheduling\BookingTimezone;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ReservationServiceTest extends KernelTestCase
{
    private const MONDAY = '2026-09-14';

    private ReservationService $service;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        /** @var ReservationService $service */
        $service = $container->get(ReservationService::class);
        /** @var Connection $connection */
        $connection = $container->get('doctrine.dbal.default_connection');

        $this->service = $service;
        $this->connection = $connection;

        $this->connection->executeStatement('DELETE FROM reservation');
        $this->connection->executeStatement('DELETE FROM holiday');
    }

    public function testBookingStoresAnActiveReservation(): void
    {
        $reservation = $this->book('09:00');

        self::assertTrue($reservation->isActive());
        self::assertSame('2026-09-14 07:00:00', $this->storedSlotStart($reservation));
    }

    public function testSameSlotCannotBeBookedTwice(): void
    {
        $this->book('09:00');

        $this->expectException(SlotAlreadyBooked::class);

        $this->book('09:00');
    }

    public function testSlotOutsideWorkingHoursIsRejected(): void
    {
        $this->expectException(SlotNotBookable::class);

        $this->book('08:00');
    }

    public function testSlotNotAlignedToTheGridIsRejected(): void
    {
        $this->expectException(SlotNotBookable::class);

        $this->book('09:15');
    }

    public function testSlotOnAClosedDayIsRejected(): void
    {
        $this->expectException(SlotNotBookable::class);

        $this->service->book(
            new DateTimeImmutable('2026-09-20 10:00', BookingTimezone::get()),
            'Anna Kowalska',
            'anna.kowalska@example.com',
        );
    }

    public function testSlotOnAHolidayIsRejected(): void
    {
        $this->connection->insert('holiday', ['date' => self::MONDAY, 'name' => 'Dzien testowy']);

        $this->expectException(SlotNotBookable::class);

        $this->book('09:00');
    }

    public function testCancellingMarksTheReservationAsCancelled(): void
    {
        $reservation = $this->book('09:00');

        $this->service->cancel($reservation->id());

        self::assertSame(ReservationStatus::Cancelled->value, $this->storedStatus($reservation));
    }

    public function testCancellingFreesTheSlotForSomebodyElse(): void
    {
        $first = $this->book('09:00');
        $this->service->cancel($first->id());

        $second = $this->book('09:00');

        self::assertTrue($second->isActive());
        self::assertNotSame($first->id()->toRfc4122(), $second->id()->toRfc4122());
    }

    public function testCancellingUnknownReservationFails(): void
    {
        $this->expectException(ReservationNotFound::class);

        $this->service->cancel(Uuid::v7());
    }

    public function testCancellingTwiceFails(): void
    {
        $reservation = $this->book('09:00');
        $this->service->cancel($reservation->id());

        $this->expectException(ReservationAlreadyCancelled::class);

        $this->service->cancel($reservation->id());
    }

    private function book(string $localTime): Reservation
    {
        return $this->service->book(
            new DateTimeImmutable(self::MONDAY . ' ' . $localTime, BookingTimezone::get()),
            'Anna Kowalska',
            'anna.kowalska@example.com',
        );
    }

    private function storedSlotStart(Reservation $reservation): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT slot_start FROM reservation WHERE id = ?',
            [$reservation->id()->toRfc4122()],
        );
    }

    private function storedStatus(Reservation $reservation): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT status FROM reservation WHERE id = ?',
            [$reservation->id()->toRfc4122()],
        );
    }
}
