<?php

declare(strict_types=1);

namespace App\Scheduling;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:holidays', description: 'Store Polish public holidays for a given year')]
final class SeedHolidaysCommand extends Command
{
    public function __construct(
        private readonly PolishPublicHolidays $holidays,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('year', InputArgument::OPTIONAL, 'Year to seed', date('Y'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $year = (int) $input->getArgument('year');
        $alreadyStored = $this->storedDates();
        $added = 0;

        foreach ($this->holidays->forYear($year) as $holiday) {
            if (in_array($holiday->date()->format('Y-m-d'), $alreadyStored, true)) {
                continue;
            }

            $this->entityManager->persist($holiday);
            $added++;
        }

        $this->entityManager->flush();

        (new SymfonyStyle($input, $output))->success(sprintf('Added %d holidays for %d.', $added, $year));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function storedDates(): array
    {
        return array_map(
            static fn (Holiday $holiday): string => $holiday->date()->format('Y-m-d'),
            $this->entityManager->getRepository(Holiday::class)->findAll(),
        );
    }
}
