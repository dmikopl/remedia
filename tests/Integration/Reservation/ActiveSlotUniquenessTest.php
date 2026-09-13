<?php

declare(strict_types=1);

namespace App\Tests\Integration\Reservation;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ActiveSlotUniquenessTest extends KernelTestCase
{
    private const SLOT = '2026-09-14 07:00:00';
    private const LOCK_NOT_AVAILABLE = '55P03';

    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM reservation');
    }

    public function testSecondActiveReservationForTheSameSlotIsRejected(): void
    {
        $this->book($this->connection, self::SLOT);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->book($this->connection, self::SLOT);
    }

    public function testCancelledReservationFreesTheSlot(): void
    {
        $this->book($this->connection, self::SLOT);
        $this->connection->executeStatement(
            "UPDATE reservation SET status = 'cancelled', cancelled_at = now() WHERE slot_start = ?",
            [self::SLOT],
        );

        $this->book($this->connection, self::SLOT);

        self::assertSame(1, $this->activeCount(self::SLOT));
    }

    public function testTwoCancelledReservationsCanShareASlot(): void
    {
        $this->book($this->connection, self::SLOT, 'cancelled');
        $this->book($this->connection, self::SLOT, 'cancelled');

        self::assertSame(0, $this->activeCount(self::SLOT));
    }

    public function testNeighbouringSlotsDoNotClash(): void
    {
        $this->book($this->connection, self::SLOT);
        $this->book($this->connection, '2026-09-14 07:30:00');

        self::assertSame(1, $this->activeCount(self::SLOT));
        self::assertSame(1, $this->activeCount('2026-09-14 07:30:00'));
    }

    public function testSecondConnectionCannotTakeASlotHeldByAnUncommittedInsert(): void
    {
        $other = DriverManager::getConnection($this->connection->getParams());
        $other->executeStatement("SET lock_timeout = '500ms'");

        $this->connection->beginTransaction();
        $this->book($this->connection, self::SLOT);

        $sqlState = null;

        try {
            $this->book($other, self::SLOT);
        } catch (DriverException $exception) {
            $sqlState = $exception->getSQLState();
        }

        $this->connection->rollBack();
        $other->close();

        self::assertSame(self::LOCK_NOT_AVAILABLE, $sqlState);
    }

    private function book(Connection $connection, string $slotStart, string $status = 'active'): void
    {
        $connection->insert('reservation', [
            'id' => Uuid::v7()->toRfc4122(),
            'slot_start' => $slotStart,
            'customer_name' => 'Anna Kowalska',
            'customer_email' => 'anna.kowalska@example.com',
            'status' => $status,
            'created_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);
    }

    private function activeCount(string $slotStart): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT count(*) FROM reservation WHERE slot_start = ? AND status = 'active'",
            [$slotStart],
        );
    }
}
