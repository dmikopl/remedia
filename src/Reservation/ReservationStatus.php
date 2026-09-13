<?php

declare(strict_types=1);

namespace App\Reservation;

enum ReservationStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
}
