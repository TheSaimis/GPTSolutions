<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Darbuotojų rizikos modulis: kūno dalys, rizikos kategorijos, sąrašas, įmonė–darbuotojas.
 */
final class Version20260326170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Risk module: body_part_category, body_part, risk_categories, risk_subcategories, worker, company, company_worker, risk_list';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE body_part_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE body_part (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, category_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE risk_categories (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE risk_subcategories (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, category_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE worker (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE company (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE company_worker (id INT AUTO_INCREMENT NOT NULL, company_id INT NOT NULL, worker_id INT NOT NULL, UNIQUE INDEX company_worker_unique (company_id, worker_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE risk_list (id INT AUTO_INCREMENT NOT NULL, body_part_id INT NOT NULL, risk_subcategory_id INT NOT NULL, worker_id INT NOT NULL, UNIQUE INDEX risk_list_unique (body_part_id, risk_subcategory_id, worker_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE body_part ADD CONSTRAINT FK_BODY_PART_CATEGORY FOREIGN KEY (category_id) REFERENCES body_part_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE risk_subcategories ADD CONSTRAINT FK_RISK_SUB_TO_CATEGORY FOREIGN KEY (category_id) REFERENCES risk_categories (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_worker ADD CONSTRAINT FK_COMPANY_WORKER_COMPANY FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_worker ADD CONSTRAINT FK_COMPANY_WORKER_WORKER FOREIGN KEY (worker_id) REFERENCES worker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE risk_list ADD CONSTRAINT FK_RISK_LIST_BODY_PART FOREIGN KEY (body_part_id) REFERENCES body_part (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE risk_list ADD CONSTRAINT FK_RISK_LIST_RISK_SUB FOREIGN KEY (risk_subcategory_id) REFERENCES risk_subcategories (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE risk_list ADD CONSTRAINT FK_RISK_LIST_WORKER FOREIGN KEY (worker_id) REFERENCES worker (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE risk_list DROP FOREIGN KEY FK_RISK_LIST_BODY_PART');
        $this->addSql('ALTER TABLE risk_list DROP FOREIGN KEY FK_RISK_LIST_RISK_SUB');
        $this->addSql('ALTER TABLE risk_list DROP FOREIGN KEY FK_RISK_LIST_WORKER');
        $this->addSql('ALTER TABLE company_worker DROP FOREIGN KEY FK_COMPANY_WORKER_COMPANY');
        $this->addSql('ALTER TABLE company_worker DROP FOREIGN KEY FK_COMPANY_WORKER_WORKER');
        $this->addSql('ALTER TABLE risk_subcategories DROP FOREIGN KEY FK_RISK_SUB_TO_CATEGORY');
        $this->addSql('ALTER TABLE body_part DROP FOREIGN KEY FK_BODY_PART_CATEGORY');
        $this->addSql('DROP TABLE risk_list');
        $this->addSql('DROP TABLE company_worker');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE worker');
        $this->addSql('DROP TABLE risk_subcategories');
        $this->addSql('DROP TABLE risk_categories');
        $this->addSql('DROP TABLE body_part');
        $this->addSql('DROP TABLE body_part_category');
    }
}
