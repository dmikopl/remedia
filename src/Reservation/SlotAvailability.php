<?php

declare(strict_types=1);

namespace App\Reservation;

use App\Scheduling\Slot;
use App\Scheduling\SlotGenerator;
use DateTimeImmutable;

final class SlotAvailability
{
    public function __construct(
        private readonly SlotGenerator $slotGenerator,
        private readonly ReservationRepository $reservations,
    ) {
    }

    /**
     * @return list<Slot>
     */
    public function availableOn(DateTimeImmutable $day): array
    {
        $slots = $this->slotGenerator->generateFor($day);

        if ([] === $slots) {
            return [];
        }

        $taken = array_flip($this->reservations->activeSlotStartsOn($day));

        return array_values(array_filter(
            $slots,
            static fn (Slot $slot): bool => !isset($taken[SlotKey::of($slot->start())]),
        ));
    }
}
