<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateTimeImmutable;

final class SlotGenerator
{
    public function __construct(
        private readonly WorkingHours $workingHours,
        private readonly int $slotLengthMinutes,
    ) {
    }

    /**
     * @return list<Slot>
     */
    public function generateFor(DateTimeImmutable $day): array
    {
        if (!$this->workingHours->isOpenOn($day)) {
            return [];
        }

        $cursor = $this->workingHours->opensAt($day);
        $closesAt = $this->workingHours->closesAt($day);
        $slots = [];

        while (true) {
            $slotEnd = $cursor->modify(sprintf('+%d minutes', $this->slotLengthMinutes));

            if ($slotEnd > $closesAt) {
                break;
            }

            $slots[] = new Slot($cursor, $slotEnd);
            $cursor = $slotEnd;
        }

        return $slots;
    }
}
