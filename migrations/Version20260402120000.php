<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'company_requisite: manager first name & role EN/RU; widen role column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_requisite ADD manager_first_name_en VARCHAR(255) DEFAULT NULL, ADD manager_first_name_ru VARCHAR(255) DEFAULT NULL, ADD role_en VARCHAR(255) DEFAULT NULL, ADD role_ru VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE company_requisite MODIFY role VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_requisite DROP manager_first_name_en, DROP manager_first_name_ru, DROP role_en, DROP role_ru');
        $this->addSql('ALTER TABLE company_requisite MODIFY role VARCHAR(100) DEFAULT NULL');
    }
}
