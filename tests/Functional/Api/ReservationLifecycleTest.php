<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReservationLifecycleTest extends WebTestCase
{
    private const DAY = '2026-09-14';
    private const SLOT = '2026-09-14T09:00:00+02:00';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->connection()->executeStatement('DELETE FROM reservation');
        $this->connection()->executeStatement('DELETE FROM holiday');
    }

    public function testSlotIsBookedCancelledAndBookedAgain(): void
    {
        self::assertContains(self::SLOT, $this->availableSlots());

        $first = $this->book();

        self::assertResponseStatusCodeSame(201);
        self::assertNotContains(self::SLOT, $this->availableSlots());

        $this->book();

        self::assertResponseStatusCodeSame(409);
        self::assertSame('slot_already_booked', $this->payload()['error']);

        $this->cancel($first);

        self::assertResponseStatusCodeSame(204);
        self::assertContains(self::SLOT, $this->availableSlots());

        $second = $this->book();

        self::assertResponseStatusCodeSame(201);
        self::assertNotSame($first, $second);
        self::assertNotContains(self::SLOT, $this->availableSlots());
    }

    /**
     * @return list<string>
     */
    private function availableSlots(): array
    {
        $this->client->request('GET', '/api/slots?date=' . self::DAY);

        self::assertResponseIsSuccessful();

        /** @var list<array{start: string, end: string}> $slots */
        $slots = $this->payload()['slots'];

        return array_column($slots, 'start');
    }

    private function book(): string
    {
        $this->client->request(
            'POST',
            '/api/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'slotStart' => self::SLOT,
                'customerName' => 'Anna Kowalska',
                'customerEmail' => 'anna.kowalska@example.com',
            ], JSON_THROW_ON_ERROR),
        );

        return (string) ($this->payload()['id'] ?? '');
    }

    private function cancel(string $id): void
    {
        $this->client->request('POST', sprintf('/api/reservations/%s/cancel', $id));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $content = $this->client->getResponse()->getContent();

        if (!is_string($content) || '' === $content) {
            return [];
        }

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
