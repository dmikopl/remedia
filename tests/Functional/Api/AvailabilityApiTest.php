<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class AvailabilityApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->connection()->executeStatement('DELETE FROM reservation');
        $this->connection()->executeStatement('DELETE FROM holiday');
    }

    public function testWorkingDayListsEverySlot(): void
    {
        $this->client->request('GET', '/api/slots?date=2026-09-14');

        self::assertResponseIsSuccessful();

        $payload = $this->payload();

        self::assertSame('2026-09-14', $payload['date']);
        self::assertCount(16, $payload['slots']);
        self::assertSame('2026-09-14T09:00:00+02:00', $payload['slots'][0]['start']);
        self::assertSame('2026-09-14T09:30:00+02:00', $payload['slots'][0]['end']);
    }

    public function testSaturdayStopsBeforeClosingTime(): void
    {
        $this->client->request('GET', '/api/slots?date=2026-09-19');

        $payload = $this->payload();

        self::assertCount(8, $payload['slots']);
        self::assertSame('2026-09-19T14:00:00+02:00', $payload['slots'][7]['end']);
    }

    public function testSundayListsNothing(): void
    {
        $this->client->request('GET', '/api/slots?date=2026-09-20');

        self::assertSame([], $this->payload()['slots']);
    }

    public function testHolidayListsNothing(): void
    {
        $this->connection()->insert('holiday', ['date' => '2026-11-11', 'name' => 'Swieto Niepodleglosci']);

        $this->client->request('GET', '/api/slots?date=2026-11-11');

        self::assertSame([], $this->payload()['slots']);
    }

    public function testBookedSlotIsNoLongerListed(): void
    {
        $this->reserve('2026-09-14 07:00:00');

        $this->client->request('GET', '/api/slots?date=2026-09-14');

        $starts = array_column($this->payload()['slots'], 'start');

        self::assertCount(15, $starts);
        self::assertNotContains('2026-09-14T09:00:00+02:00', $starts);
    }

    public function testMalformedDateIsRejected(): void
    {
        $this->client->request('GET', '/api/slots?date=14-09-2026');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_date', $this->payload()['error']);
    }

    public function testDateThatDoesNotExistInTheCalendarIsRejected(): void
    {
        $this->client->request('GET', '/api/slots?date=2026-02-30');

        self::assertResponseStatusCodeSame(400);
    }

    private function reserve(string $slotStartInUtc): void
    {
        $this->connection()->insert('reservation', [
            'id' => Uuid::v7()->toRfc4122(),
            'slot_start' => $slotStartInUtc,
            'customer_name' => 'Anna Kowalska',
            'customer_email' => 'anna.kowalska@example.com',
            'status' => 'active',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
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
