<?php

declare(strict_types=1);

namespace App\Reservation\Exception;

use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

final class SlotNotBookable extends RuntimeException
{
    public static function at(DateTimeImmutable $slotStart): self
    {
        return new self(sprintf('%s is not a bookable slot.', $slotStart->format(DateTimeInterface::ATOM)));
    }
}
