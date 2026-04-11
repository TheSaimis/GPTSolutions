<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AAP: kiekis (vnt/poros) priskyrimuose — worker_item, company_worker_equipment, aap_equipment_group_equipment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worker_item ADD quantity INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE company_worker_equipment ADD quantity INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE aap_equipment_group_equipment ADD quantity INT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worker_item DROP quantity');
        $this->addSql('ALTER TABLE company_worker_equipment DROP quantity');
        $this->addSql('ALTER TABLE aap_equipment_group_equipment DROP quantity');
    }
}
