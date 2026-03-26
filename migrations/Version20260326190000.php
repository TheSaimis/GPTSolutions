<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pridėta risk_groups lentelė, risk_categories.group_id FK, risk_subcategories.category_id nullable + group_id FK';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE risk_groups (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            line_number INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE risk_categories ADD group_id INT NOT NULL');
        $this->addSql('ALTER TABLE risk_categories ADD CONSTRAINT FK_RISK_CAT_GROUP FOREIGN KEY (group_id) REFERENCES risk_groups (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_RISK_CAT_GROUP ON risk_categories (group_id)');

        $this->addSql('ALTER TABLE risk_subcategories MODIFY category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE risk_subcategories ADD group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE risk_subcategories ADD CONSTRAINT FK_RISK_SUB_GROUP FOREIGN KEY (group_id) REFERENCES risk_groups (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_RISK_SUB_GROUP ON risk_subcategories (group_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE risk_subcategories DROP FOREIGN KEY FK_RISK_SUB_GROUP');
        $this->addSql('DROP INDEX IDX_RISK_SUB_GROUP ON risk_subcategories');
        $this->addSql('ALTER TABLE risk_subcategories DROP group_id');
        $this->addSql('ALTER TABLE risk_subcategories MODIFY category_id INT NOT NULL');

        $this->addSql('ALTER TABLE risk_categories DROP FOREIGN KEY FK_RISK_CAT_GROUP');
        $this->addSql('DROP INDEX IDX_RISK_CAT_GROUP ON risk_categories');
        $this->addSql('ALTER TABLE risk_categories DROP group_id');

        $this->addSql('DROP TABLE risk_groups');
    }
}
