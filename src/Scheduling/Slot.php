<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateTimeImmutable;

final class Slot
{
    public function __construct(
        private readonly DateTimeImmutable $start,
        private readonly DateTimeImmutable $end,
    ) {
    }

    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): DateTimeImmutable
    {
        return $this->end;
    }
}
