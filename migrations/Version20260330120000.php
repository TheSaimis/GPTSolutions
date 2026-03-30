<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category table and company_requisite.category_id foreign key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            UNIQUE INDEX UNIQ_64C19C15E237E06 (name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE company_requisite ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE company_requisite ADD CONSTRAINT FK_COMPANY_REQUISITE_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_COMPANY_REQUISITE_CATEGORY ON company_requisite (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_requisite DROP FOREIGN KEY FK_COMPANY_REQUISITE_CATEGORY');
        $this->addSql('DROP INDEX IDX_COMPANY_REQUISITE_CATEGORY ON company_requisite');
        $this->addSql('ALTER TABLE company_requisite DROP category_id');
        $this->addSql('DROP TABLE category');
    }
}

