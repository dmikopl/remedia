<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateTimeImmutable;

final class PolishPublicHolidays
{
    private const FIXED = [
        '01-01' => 'Nowy Rok',
        '01-06' => 'Święto Trzech Króli',
        '05-01' => 'Święto Pracy',
        '05-03' => 'Święto Narodowe Trzeciego Maja',
        '08-15' => 'Wniebowzięcie Najświętszej Maryi Panny',
        '11-01' => 'Wszystkich Świętych',
        '11-11' => 'Narodowe Święto Niepodległości',
        '12-25' => 'Boże Narodzenie',
        '12-26' => 'Drugi dzień Bożego Narodzenia',
    ];

    private const AFTER_EASTER = [
        0 => 'Wielkanoc',
        1 => 'Poniedziałek Wielkanocny',
        49 => 'Zielone Świątki',
        60 => 'Boże Ciało',
    ];

    /**
     * @return list<Holiday>
     */
    public function forYear(int $year): array
    {
        $holidays = [];

        foreach (self::FIXED as $monthAndDay => $name) {
            $holidays[] = new Holiday($this->date(sprintf('%d-%s', $year, $monthAndDay)), $name);
        }

        $easter = $this->easterSunday($year);

        foreach (self::AFTER_EASTER as $offset => $name) {
            $holidays[] = new Holiday($easter->modify(sprintf('+%d days', $offset)), $name);
        }

        usort($holidays, static fn (Holiday $left, Holiday $right): int => $left->date() <=> $right->date());

        return $holidays;
    }

    private function easterSunday(int $year): DateTimeImmutable
    {
        return $this->date(sprintf('%d-03-21', $year))->modify(sprintf('+%d days', easter_days($year)));
    }

    private function date(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, BookingTimezone::get());
    }
}
