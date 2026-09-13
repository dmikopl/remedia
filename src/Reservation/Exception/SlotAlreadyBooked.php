<?php

declare(strict_types=1);

namespace App\Reservation\Exception;

use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

final class SlotAlreadyBooked extends RuntimeException
{
    public static function at(DateTimeImmutable $slotStart): self
    {
        return new self(sprintf('Slot %s is already taken.', $slotStart->format(DateTimeInterface::ATOM)));
    }
}
