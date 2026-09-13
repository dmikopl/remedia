<?php

declare(strict_types=1);

namespace App\Reservation\Http;

use App\Reservation\Exception\ReservationAlreadyCancelled;
use App\Reservation\Exception\ReservationNotFound;
use App\Reservation\Exception\SlotAlreadyBooked;
use App\Reservation\Exception\SlotNotBookable;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener]
final class ApiExceptionListener
{
    private const DOMAIN_ERRORS = [
        SlotNotBookable::class => ['slot_not_bookable', Response::HTTP_UNPROCESSABLE_ENTITY],
        SlotAlreadyBooked::class => ['slot_already_booked', Response::HTTP_CONFLICT],
        ReservationNotFound::class => ['reservation_not_found', Response::HTTP_NOT_FOUND],
        ReservationAlreadyCancelled::class => ['reservation_already_cancelled', Response::HTTP_CONFLICT],
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $domainError = self::DOMAIN_ERRORS[$exception::class] ?? null;

        if (null !== $domainError) {
            [$code, $status] = $domainError;

            $event->setResponse(new JsonResponse(['error' => $code, 'message' => $exception->getMessage()], $status));

            return;
        }

        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof ValidationFailedException) {
            $event->setResponse(new JsonResponse([
                'error' => 'validation_failed',
                'message' => 'The request payload is invalid.',
                'violations' => self::violationsOf($previous),
            ], $exception->getStatusCode()));

            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'invalid_request',
            'message' => $exception->getMessage(),
        ], $exception->getStatusCode()));
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private static function violationsOf(ValidationFailedException $exception): array
    {
        $violations = [];

        foreach ($exception->getViolations() as $violation) {
            $violations[] = [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $violations;
    }
}
