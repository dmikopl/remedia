<?php

declare(strict_types=1);

namespace App\Reservation;

use App\Scheduling\BookingTimezone;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class ReservationRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $id): ?Reservation
    {
        return $this->entityManager->find(Reservation::class, $id);
    }

    public function save(Reservation $reservation): void
    {
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();
    }

    /**
     * @return list<string>
     */
    public function activeSlotStartsOn(DateTimeImmutable $day): array
    {
        $from = $day->setTimezone(BookingTimezone::get())->setTime(0, 0);

        /** @var list<string> $starts */
        $starts = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT slot_start FROM reservation WHERE status = ? AND slot_start >= ? AND slot_start < ?',
            [
                ReservationStatus::Active->value,
                SlotKey::of($from),
                SlotKey::of($from->modify('+1 day')),
            ],
        );

        return $starts;
    }
}
