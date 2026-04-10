<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Equipment: matavimo vienetas (vnt / poros) Word stulpeliui ${vnt}';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE equipment ADD unit_of_measurement VARCHAR(32) NOT NULL DEFAULT 'vnt'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment DROP unit_of_measurement');
    }
}
