<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reservations table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reservations (
            id UUID NOT NULL,
            restaurant_id UUID NOT NULL,
            date DATE NOT NULL,
            time_slot VARCHAR(5) NOT NULL,
            num_people INT NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) DEFAULT NULL,
            customer_email VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_reservations_restaurant ON reservations (restaurant_id)');
        $this->addSql('CREATE INDEX idx_reservations_date_slot ON reservations (restaurant_id, date, time_slot)');
        $this->addSql('CREATE INDEX idx_reservations_status ON reservations (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS reservations');
    }
}
