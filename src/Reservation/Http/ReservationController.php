<?php

declare(strict_types=1);

namespace App\Reservation\Http;

use App\Reservation\Reservation;
use App\Reservation\ReservationService;
use App\Scheduling\BookingTimezone;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[AsController]
final class ReservationController
{
    public function __construct(private readonly ReservationService $reservations)
    {
    }

    #[Route('/api/reservations', name: 'api_reservation_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] BookReservationRequest $payload): JsonResponse
    {
        $reservation = $this->reservations->book(
            new DateTimeImmutable($payload->slotStart),
            $payload->customerName,
            $payload->customerEmail,
        );

        return new JsonResponse($this->represent($reservation), Response::HTTP_CREATED);
    }

    #[Route(
        '/api/reservations/{id}/cancel',
        name: 'api_reservation_cancel',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function cancel(string $id): Response
    {
        $this->reservations->cancel(Uuid::fromString($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, string>
     */
    private function represent(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id()->toRfc4122(),
            'slotStart' => $reservation->slotStart()
                ->setTimezone(BookingTimezone::get())
                ->format(DateTimeInterface::ATOM),
            'customerName' => $reservation->customerName(),
            'customerEmail' => $reservation->customerEmail(),
            'status' => $reservation->status()->value,
        ];
    }
}
