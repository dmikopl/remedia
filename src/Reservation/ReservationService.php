<?php

declare(strict_types=1);

namespace App\Reservation;

use App\Reservation\Exception\ReservationAlreadyCancelled;
use App\Reservation\Exception\ReservationNotFound;
use App\Reservation\Exception\SlotAlreadyBooked;
use App\Reservation\Exception\SlotNotBookable;
use App\Scheduling\BookingTimezone;
use App\Scheduling\SlotGenerator;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final class ReservationService
{
    public function __construct(
        private readonly SlotGenerator $slotGenerator,
        private readonly ReservationRepository $reservations,
    ) {
    }

    public function book(DateTimeImmutable $slotStart, string $customerName, string $customerEmail): Reservation
    {
        $this->assertSlotIsBookable($slotStart);

        $reservation = new Reservation($slotStart, $customerName, $customerEmail);

        try {
            $this->reservations->save($reservation);
        } catch (UniqueConstraintViolationException) {
            throw SlotAlreadyBooked::at($slotStart);
        }

        return $reservation;
    }

    public function cancel(Uuid $id): void
    {
        $reservation = $this->reservations->find($id);

        if (null === $reservation) {
            throw ReservationNotFound::withId($id);
        }

        if (!$reservation->isActive()) {
            throw ReservationAlreadyCancelled::withId($id);
        }

        $reservation->cancel();
        $this->reservations->save($reservation);
    }

    private function assertSlotIsBookable(DateTimeImmutable $slotStart): void
    {
        $wanted = SlotKey::of($slotStart);
        $day = $slotStart->setTimezone(BookingTimezone::get());

        foreach ($this->slotGenerator->generateFor($day) as $slot) {
            if (SlotKey::of($slot->start()) === $wanted) {
                return;
            }
        }

        throw SlotNotBookable::at($slotStart);
    }
}
