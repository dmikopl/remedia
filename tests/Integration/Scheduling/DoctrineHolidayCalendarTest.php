<?php

declare(strict_types=1);

namespace App\Tests\Integration\Scheduling;

use App\Scheduling\BookingTimezone;
use App\Scheduling\DoctrineHolidayCalendar;
use App\Scheduling\Holiday;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineHolidayCalendarTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineHolidayCalendar $calendar;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->entityManager = $entityManager;
        $this->calendar = new DoctrineHolidayCalendar($entityManager);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();

        parent::tearDown();
    }

    public function testRecognisesStoredHoliday(): void
    {
        $this->store('2026-11-11');

        self::assertTrue($this->calendar->isHoliday($this->warsawDay('2026-11-11')));
    }

    public function testOrdinaryDayIsNotAHoliday(): void
    {
        $this->store('2026-11-11');

        self::assertFalse($this->calendar->isHoliday($this->warsawDay('2026-11-10')));
    }

    public function testDayIsResolvedInWarsawTimeEvenWhenGivenInUtc(): void
    {
        $this->store('2026-11-11');

        $stillTheTenthInUtc = new DateTimeImmutable('2026-11-10 23:30', new DateTimeZone('UTC'));

        self::assertTrue($this->calendar->isHoliday($stillTheTenthInUtc));
    }

    private function store(string $date): void
    {
        $this->entityManager->persist(new Holiday($this->warsawDay($date), 'Test holiday'));
        $this->entityManager->flush();
    }

    private function warsawDay(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, BookingTimezone::get());
    }
}
