<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create orders and order_lines tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE orders (
            id UUID NOT NULL,
            restaurant_id UUID NOT NULL,
            status VARCHAR(20) NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT \'manual\',
            table_number VARCHAR(50) DEFAULT NULL,
            customer_phone VARCHAR(50) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_orders_restaurant ON orders (restaurant_id)');
        $this->addSql('CREATE INDEX idx_orders_status ON orders (status)');

        $this->addSql('CREATE TABLE order_lines (
            id UUID NOT NULL,
            order_id UUID NOT NULL,
            menu_item_id UUID NOT NULL,
            menu_item_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL,
            unit_price DOUBLE PRECISION NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT \'EUR\',
            PRIMARY KEY(id),
            CONSTRAINT fk_order_line_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE INDEX idx_order_lines_order ON order_lines (order_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS order_lines');
        $this->addSql('DROP TABLE IF EXISTS orders');
    }
}
