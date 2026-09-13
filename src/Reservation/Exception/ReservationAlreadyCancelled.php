<?php

declare(strict_types=1);

namespace App\Reservation\Exception;

use RuntimeException;
use Symfony\Component\Uid\Uuid;

final class ReservationAlreadyCancelled extends RuntimeException
{
    public static function withId(Uuid $id): self
    {
        return new self(sprintf('Reservation %s has already been cancelled.', $id->toRfc4122()));
    }
}
