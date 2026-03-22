<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260321120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Soft-delete laukai User/CompanyRequisite + AuditLog lentelė';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD deleted TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE user ADD deleted_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE company_requisite ADD deleted TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE company_requisite ADD deleted_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE audit_log (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            action VARCHAR(1000) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP deleted, DROP deleted_date');
        $this->addSql('ALTER TABLE company_requisite DROP deleted, DROP deleted_date');
        $this->addSql('DROP TABLE audit_log');
    }
}
