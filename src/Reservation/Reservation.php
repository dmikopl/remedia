<?php

declare(strict_types=1);

namespace App\Reservation;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'reservation')]
#[ORM\UniqueConstraint(
    name: 'uniq_reservation_active_slot',
    columns: ['slot_start'],
    options: ['where' => "((status)::text = 'active'::text)"],
)]
class Reservation
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $slotStart;

    #[ORM\Column(length: 120)]
    private string $customerName;

    #[ORM\Column(length: 180)]
    private string $customerEmail;

    #[ORM\Column(length: 20, enumType: ReservationStatus::class)]
    private ReservationStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    public function __construct(DateTimeImmutable $slotStart, string $customerName, string $customerEmail)
    {
        $this->id = Uuid::v7();
        $this->slotStart = self::inUtc($slotStart);
        $this->customerName = $customerName;
        $this->customerEmail = $customerEmail;
        $this->status = ReservationStatus::Active;
        $this->createdAt = self::inUtc(new DateTimeImmutable());
    }

    public function cancel(): void
    {
        $this->status = ReservationStatus::Cancelled;
        $this->cancelledAt = self::inUtc(new DateTimeImmutable());
    }

    public function isActive(): bool
    {
        return ReservationStatus::Active === $this->status;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function slotStart(): DateTimeImmutable
    {
        return $this->slotStart;
    }

    public function customerName(): string
    {
        return $this->customerName;
    }

    public function customerEmail(): string
    {
        return $this->customerEmail;
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    private static function inUtc(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone(new DateTimeZone('UTC'));
    }
}
