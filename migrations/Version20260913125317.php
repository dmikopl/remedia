<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913125317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reservation table with a unique index over active slots only';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reservation (id UUID NOT NULL, slot_start TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, customer_name VARCHAR(120) NOT NULL, customer_email VARCHAR(180) NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_reservation_active_slot ON reservation (slot_start) WHERE (status = \'active\')');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reservation');
    }
}
