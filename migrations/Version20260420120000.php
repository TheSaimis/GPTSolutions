<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'company_requisite: AAP kortelių „Pagrindas išduoti“ tekstas (${pagrindas})';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_requisite ADD aap_korteles_pagrindas LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_requisite DROP aap_korteles_pagrindas');
    }
}
