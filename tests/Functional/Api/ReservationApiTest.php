<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ReservationApiTest extends WebTestCase
{
    private const SLOT = '2026-09-14T09:00:00+02:00';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->connection()->executeStatement('DELETE FROM reservation');
        $this->connection()->executeStatement('DELETE FROM holiday');
    }

    public function testBookingReturnsTheCreatedReservation(): void
    {
        $this->book(self::SLOT);

        self::assertResponseStatusCodeSame(201);

        $payload = $this->payload();

        self::assertTrue(Uuid::isValid((string) $payload['id']));
        self::assertSame(self::SLOT, $payload['slotStart']);
        self::assertSame('active', $payload['status']);
        self::assertSame('anna.kowalska@example.com', $payload['customerEmail']);
    }

    public function testSameSlotCannotBeBookedTwice(): void
    {
        $this->book(self::SLOT);
        $this->book(self::SLOT);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('slot_already_booked', $this->payload()['error']);
    }

    public function testSlotOutsideWorkingHoursIsRejected(): void
    {
        $this->book('2026-09-14T08:00:00+02:00');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('slot_not_bookable', $this->payload()['error']);
    }

    public function testSlotOffTheHalfHourGridIsRejected(): void
    {
        $this->book('2026-09-14T09:15:00+02:00');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('slot_not_bookable', $this->payload()['error']);
    }

    public function testInvalidPayloadIsReportedWithViolations(): void
    {
        $this->post('/api/reservations', [
            'slotStart' => self::SLOT,
            'customerName' => '',
            'customerEmail' => 'not-an-email',
        ]);

        self::assertResponseStatusCodeSame(422);

        $payload = $this->payload();

        self::assertSame('validation_failed', $payload['error']);
        self::assertNotEmpty($payload['violations']);
        self::assertSame(
            ['customerName', 'customerEmail'],
            array_column((array) $payload['violations'], 'field'),
        );
    }

    public function testSlotStartWithoutTimezoneIsRejected(): void
    {
        $this->book('2026-09-14 09:00');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->payload()['error']);
    }

    public function testCancellingReturnsNoContentAndFreesTheSlot(): void
    {
        $this->book(self::SLOT);
        $id = (string) $this->payload()['id'];

        $this->client->request('POST', sprintf('/api/reservations/%s/cancel', $id));

        self::assertResponseStatusCodeSame(204);
        self::assertEmpty($this->client->getResponse()->getContent());

        $this->book(self::SLOT);

        self::assertResponseStatusCodeSame(201);
    }

    public function testCancellingUnknownReservationReturnsNotFound(): void
    {
        $this->client->request('POST', sprintf('/api/reservations/%s/cancel', Uuid::v7()->toRfc4122()));

        self::assertResponseStatusCodeSame(404);
        self::assertSame('reservation_not_found', $this->payload()['error']);
    }

    public function testCancellingTwiceReturnsConflict(): void
    {
        $this->book(self::SLOT);
        $id = (string) $this->payload()['id'];

        $this->client->request('POST', sprintf('/api/reservations/%s/cancel', $id));
        $this->client->request('POST', sprintf('/api/reservations/%s/cancel', $id));

        self::assertResponseStatusCodeSame(409);
        self::assertSame('reservation_already_cancelled', $this->payload()['error']);
    }

    public function testMalformedIdentifierDoesNotMatchTheRoute(): void
    {
        $this->client->request('POST', '/api/reservations/not-a-uuid/cancel');

        self::assertResponseStatusCodeSame(404);
    }

    private function book(string $slotStart): void
    {
        $this->post('/api/reservations', [
            'slotStart' => $slotStart,
            'customerName' => 'Anna Kowalska',
            'customerEmail' => 'anna.kowalska@example.com',
        ]);
    }

    /**
     * @param array<string, string> $payload
     */
    private function post(string $uri, array $payload): void
    {
        $this->client->request(
            'POST',
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $content = $this->client->getResponse()->getContent();

        self::assertIsString($content);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function connection(): Connection
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }
}
