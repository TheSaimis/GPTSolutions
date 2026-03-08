<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308133500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add manager_gender column to company_requisite';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_requisite ADD manager_gender VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_requisite DROP manager_gender');
    }
}
