<?php

declare(strict_types=1);

namespace App\Reservation;

use DateTimeImmutable;
use DateTimeZone;

final class SlotKey
{
    public static function of(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
