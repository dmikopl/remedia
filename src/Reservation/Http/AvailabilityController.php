<?php

declare(strict_types=1);

namespace App\Reservation\Http;

use App\Reservation\SlotAvailability;
use App\Scheduling\BookingTimezone;
use App\Scheduling\Slot;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class AvailabilityController
{
    public function __construct(private readonly SlotAvailability $availability)
    {
    }

    #[Route('/api/slots', name: 'api_slots', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $date = $request->query->getString('date');
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, BookingTimezone::get());

        if (false === $day || $day->format('Y-m-d') !== $date) {
            return new JsonResponse([
                'error' => 'invalid_date',
                'message' => 'Parameter "date" must be a calendar date in YYYY-MM-DD form.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'date' => $day->format('Y-m-d'),
            'slots' => array_map(
                static fn (Slot $slot): array => [
                    'start' => $slot->start()->format(DateTimeInterface::ATOM),
                    'end' => $slot->end()->format(DateTimeInterface::ATOM),
                ],
                $this->availability->availableOn($day),
            ),
        ]);
    }
}
