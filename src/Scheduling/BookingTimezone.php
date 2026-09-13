<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateTimeZone;

final class BookingTimezone
{
    public const NAME = 'Europe/Warsaw';

    public static function get(): DateTimeZone
    {
        return new DateTimeZone(self::NAME);
    }
}
