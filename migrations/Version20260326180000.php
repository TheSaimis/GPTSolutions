<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add line_number to body_part_category, body_part, risk_categories, risk_subcategories';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE body_part_category ADD line_number INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE body_part ADD line_number INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE risk_categories ADD line_number INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE risk_subcategories ADD line_number INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE risk_subcategories DROP line_number');
        $this->addSql('ALTER TABLE risk_categories DROP line_number');
        $this->addSql('ALTER TABLE body_part DROP line_number');
        $this->addSql('ALTER TABLE body_part_category DROP line_number');
    }
}
