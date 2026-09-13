<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseConnectionTest extends KernelTestCase
{
    private function connection(): Connection
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }

    public function testRunsAgainstPostgres(): void
    {
        self::assertInstanceOf(PostgreSQLPlatform::class, $this->connection()->getDatabasePlatform());
    }

    /**
     * Guards against running the suite on the development database.
     */
    public function testUsesSeparateDatabase(): void
    {
        self::assertSame('app_test', $this->connection()->getDatabase());
    }

    public function testMigrationsWereApplied(): void
    {
        $tables = $this->connection()->createSchemaManager()->listTableNames();

        self::assertContains('doctrine_migration_versions', $tables);
    }
}
