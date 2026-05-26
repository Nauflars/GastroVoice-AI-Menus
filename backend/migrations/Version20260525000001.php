<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create admin_users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_users (
            id UUID NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            restaurant_id UUID DEFAULT NULL,
            roles JSON NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_admin_users_email ON admin_users (email)');
        $this->addSql('COMMENT ON COLUMN admin_users.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN admin_users.restaurant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN admin_users.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE admin_users');
    }
}
