<?php

declare(strict_types=1);

namespace App\Reservation\Http;

use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class BookReservationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\DateTime(format: DateTimeInterface::ATOM)]
        public readonly string $slotStart = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public readonly string $customerName = '',
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public readonly string $customerEmail = '',
    ) {
    }
}
