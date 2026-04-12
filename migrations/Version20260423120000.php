<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * AAP grupės: daug-su-daug su įmonėmis (aap_equipment_company_group); pašalinta grupė ↔ darbuotojas.
 */
final class Version20260423120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AAP equipment groups: M:N company–group junction; drop aap_equipment_group_worker';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE aap_equipment_company_group (
            id INT AUTO_INCREMENT NOT NULL,
            company_id INT NOT NULL,
            group_id INT NOT NULL,
            sort_order INT DEFAULT 0 NOT NULL,
            UNIQUE INDEX uniq_aap_company_group (company_id, group_id),
            INDEX IDX_AAP_CG_COMPANY (company_id),
            INDEX IDX_AAP_CG_GROUP (group_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE aap_equipment_company_group ADD CONSTRAINT FK_AAP_CG_COMPANY FOREIGN KEY (company_id) REFERENCES company_requisite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE aap_equipment_company_group ADD CONSTRAINT FK_AAP_CG_GROUP FOREIGN KEY (group_id) REFERENCES aap_equipment_group (id) ON DELETE CASCADE');

        $this->addSql('INSERT INTO aap_equipment_company_group (company_id, group_id, sort_order)
            SELECT company_id, id, sort_order FROM aap_equipment_group');

        $this->addSql('ALTER TABLE aap_equipment_group_worker DROP FOREIGN KEY FK_AAP_GW_GROUP');
        $this->addSql('ALTER TABLE aap_equipment_group_worker DROP FOREIGN KEY FK_AAP_GW_WORKER');
        $this->addSql('DROP TABLE aap_equipment_group_worker');

        $this->addSql('ALTER TABLE aap_equipment_group DROP FOREIGN KEY FK_AAP_EQ_GROUP_COMPANY');
        $this->addSql('ALTER TABLE aap_equipment_group DROP COLUMN company_id');
        $this->addSql('ALTER TABLE aap_equipment_group DROP COLUMN sort_order');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Cannot safely reverse company–group many-to-many migration.');
    }
}
