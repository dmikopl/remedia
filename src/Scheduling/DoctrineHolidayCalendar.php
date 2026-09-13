<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineHolidayCalendar implements HolidayCalendar
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function isHoliday(DateTimeImmutable $day): bool
    {
        $date = $day->setTimezone(BookingTimezone::get())->setTime(0, 0);

        return null !== $this->entityManager->getRepository(Holiday::class)->findOneBy(['date' => $date]);
    }
}
