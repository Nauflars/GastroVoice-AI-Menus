<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create menu_categories and menu_items tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE menu_categories (
            id UUID NOT NULL,
            restaurant_id UUID NOT NULL,
            name VARCHAR(150) NOT NULL,
            display_order INT NOT NULL DEFAULT 0,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_menu_categories_restaurant ON menu_categories (restaurant_id)');

        $this->addSql('CREATE TABLE menu_items (
            id UUID NOT NULL,
            category_id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            price_amount DOUBLE PRECISION NOT NULL,
            price_currency VARCHAR(3) NOT NULL DEFAULT \'EUR\',
            is_available BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT fk_menu_item_category FOREIGN KEY (category_id) REFERENCES menu_categories (id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE INDEX idx_menu_items_category ON menu_items (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS menu_items');
        $this->addSql('DROP TABLE IF EXISTS menu_categories');
    }
}
