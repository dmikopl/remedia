<?php

declare(strict_types=1);

namespace App\Reservation;

use App\Scheduling\BookingTimezone;
use App\Scheduling\Slot;
use App\Scheduling\SlotGenerator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:seed:reservations', description: 'Fill the reservation table with a large volume of data')]
final class SeedReservationsCommand extends Command
{
    private const BATCH_SIZE = 1000;
    private const STATUSES_PER_SLOT = ['active', 'cancelled', 'cancelled', 'cancelled'];

    public function __construct(
        private readonly SlotGenerator $slotGenerator,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('count', InputArgument::OPTIONAL, 'How many rows to insert', '100000');
        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'First day to fill', date('Y-m-d'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = (int) $input->getArgument('count');
        $day = new DateTimeImmutable((string) $input->getOption('from'), BookingTimezone::get());
        $startedAt = microtime(true);

        $rows = [];
        $queued = 0;
        $inserted = 0;

        while ($queued < $target) {
            foreach ($this->slotGenerator->generateFor($day) as $slot) {
                foreach (self::STATUSES_PER_SLOT as $status) {
                    $rows[] = $this->row($slot, $status);
                    $queued++;

                    if (count($rows) >= self::BATCH_SIZE) {
                        $inserted += $this->insert($rows);
                        $rows = [];
                    }

                    if ($queued >= $target) {
                        break 3;
                    }
                }
            }

            $day = $day->modify('+1 day');
        }

        $inserted += $this->insert($rows);

        (new SymfonyStyle($input, $output))->success(sprintf(
            'Inserted %d rows up to %s in %.1fs.',
            $inserted,
            $day->format('Y-m-d'),
            microtime(true) - $startedAt,
        ));

        return Command::SUCCESS;
    }

    /**
     * @return list<string|null>
     */
    private function row(Slot $slot, string $status): array
    {
        $person = random_int(1, 100000);
        $now = gmdate('Y-m-d H:i:s');

        return [
            Uuid::v7()->toRfc4122(),
            SlotKey::of($slot->start()),
            sprintf('Customer %d', $person),
            sprintf('customer%d@example.com', $person),
            $status,
            $now,
            'cancelled' === $status ? $now : null,
        ];
    }

    /**
     * @param list<list<string|null>> $rows
     */
    private function insert(array $rows): int
    {
        if ([] === $rows) {
            return 0;
        }

        $values = implode(',', array_fill(0, count($rows), '(?,?,?,?,?,?,?)'));

        return (int) $this->connection->executeStatement(
            'INSERT INTO reservation (id, slot_start, customer_name, customer_email, status, created_at, cancelled_at)'
            . ' VALUES ' . $values
            . ' ON CONFLICT DO NOTHING',
            array_merge(...$rows),
        );
    }
}
