<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add EN/RU company requisite fields for name, address, city/district, and manager surname';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_requisite ADD company_name_en VARCHAR(255) DEFAULT NULL, ADD company_name_ru VARCHAR(255) DEFAULT NULL, ADD address_en VARCHAR(255) DEFAULT NULL, ADD address_ru VARCHAR(255) DEFAULT NULL, ADD city_or_district_en VARCHAR(255) DEFAULT NULL, ADD city_or_district_ru VARCHAR(255) DEFAULT NULL, ADD manager_last_name_en VARCHAR(255) DEFAULT NULL, ADD manager_last_name_ru VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_requisite DROP company_name_en, DROP company_name_ru, DROP address_en, DROP address_ru, DROP city_or_district_en, DROP city_or_district_ru, DROP manager_last_name_en, DROP manager_last_name_ru');
    }
}
