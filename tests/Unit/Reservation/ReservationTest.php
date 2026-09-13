<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reservation;

use App\Reservation\Reservation;
use App\Reservation\ReservationStatus;
use App\Scheduling\BookingTimezone;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class ReservationTest extends TestCase
{
    public function testIsActiveOnceCreated(): void
    {
        $reservation = $this->reservationAt('2026-09-14 09:00');

        self::assertTrue($reservation->isActive());
        self::assertSame(ReservationStatus::Active, $reservation->status());
        self::assertNull($reservation->cancelledAt());
    }

    public function testSlotStartIsStoredInUtc(): void
    {
        $reservation = $this->reservationAt('2026-09-14 09:00');

        self::assertSame('UTC', $reservation->slotStart()->getTimezone()->getName());
        self::assertSame('2026-09-14 07:00', $reservation->slotStart()->format('Y-m-d H:i'));
    }

    public function testWinterSlotKeepsTheSameLocalHourInUtc(): void
    {
        $reservation = $this->reservationAt('2026-12-14 09:00');

        self::assertSame('2026-12-14 08:00', $reservation->slotStart()->format('Y-m-d H:i'));
    }

    public function testCancellingFlipsTheStatusAndStampsTheTime(): void
    {
        $reservation = $this->reservationAt('2026-09-14 09:00');
        $reservation->cancel();

        self::assertFalse($reservation->isActive());
        self::assertSame(ReservationStatus::Cancelled, $reservation->status());
        self::assertNotNull($reservation->cancelledAt());
    }

    public function testIdentifierIsATimeSortableUuid(): void
    {
        $first = $this->reservationAt('2026-09-14 09:00');
        $second = $this->reservationAt('2026-09-14 09:30');

        self::assertInstanceOf(UuidV7::class, $first->id());
        self::assertLessThan(0, $first->id()->compare($second->id()));
    }

    private function reservationAt(string $localTime): Reservation
    {
        return new Reservation(
            new DateTimeImmutable($localTime, BookingTimezone::get()),
            'Anna Kowalska',
            'anna.kowalska@example.com',
        );
    }
}
