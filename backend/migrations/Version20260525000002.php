<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create restaurants and opening_hours tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE restaurants (
            id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            address TEXT NOT NULL,
            phone VARCHAR(50) NOT NULL,
            seat_value INT NOT NULL,
            slot_minutes INT NOT NULL,
            timezone VARCHAR(100) NOT NULL DEFAULT \'UTC\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN restaurants.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN restaurants.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN restaurants.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE opening_hours (
            id UUID NOT NULL,
            restaurant_id UUID NOT NULL,
            day_of_week SMALLINT NOT NULL,
            open_time VARCHAR(5) DEFAULT NULL,
            close_time VARCHAR(5) DEFAULT NULL,
            is_closed BOOLEAN NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_opening_hours_restaurant ON opening_hours (restaurant_id)');
        $this->addSql('COMMENT ON COLUMN opening_hours.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN opening_hours.restaurant_id IS \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE opening_hours');
        $this->addSql('DROP TABLE restaurants');
    }
}
