<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'holiday')]
class Holiday
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, unique: true)]
    private DateTimeImmutable $date;

    #[ORM\Column(length: 100)]
    private string $name;

    public function __construct(DateTimeImmutable $date, string $name)
    {
        $this->date = $date->setTime(0, 0);
        $this->name = $name;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    public function name(): string
    {
        return $this->name;
    }
}
