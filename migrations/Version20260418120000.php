<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AAP priemonių grupės (įmonė → grupė → darbuotojai + priemonės, viena Word eilutė per grupę)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE aap_equipment_group (
            id INT AUTO_INCREMENT NOT NULL,
            company_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0 NOT NULL,
            INDEX IDX_AAP_EQ_GROUP_COMPANY (company_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE aap_equipment_group ADD CONSTRAINT FK_AAP_EQ_GROUP_COMPANY FOREIGN KEY (company_id) REFERENCES company_requisite (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE aap_equipment_group_worker (
            id INT AUTO_INCREMENT NOT NULL,
            group_id INT NOT NULL,
            worker_id INT NOT NULL,
            UNIQUE INDEX uniq_aap_group_worker (group_id, worker_id),
            INDEX IDX_AAP_GW_WORKER (worker_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE aap_equipment_group_worker ADD CONSTRAINT FK_AAP_GW_GROUP FOREIGN KEY (group_id) REFERENCES aap_equipment_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE aap_equipment_group_worker ADD CONSTRAINT FK_AAP_GW_WORKER FOREIGN KEY (worker_id) REFERENCES worker (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE aap_equipment_group_equipment (
            id INT AUTO_INCREMENT NOT NULL,
            group_id INT NOT NULL,
            equipment_id INT NOT NULL,
            UNIQUE INDEX uniq_aap_group_equipment (group_id, equipment_id),
            INDEX IDX_AAP_GE_EQUIPMENT (equipment_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE aap_equipment_group_equipment ADD CONSTRAINT FK_AAP_GE_GROUP FOREIGN KEY (group_id) REFERENCES aap_equipment_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE aap_equipment_group_equipment ADD CONSTRAINT FK_AAP_GE_EQUIPMENT FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE aap_equipment_group_equipment DROP FOREIGN KEY FK_AAP_GE_EQUIPMENT');
        $this->addSql('ALTER TABLE aap_equipment_group_equipment DROP FOREIGN KEY FK_AAP_GE_GROUP');
        $this->addSql('DROP TABLE aap_equipment_group_equipment');
        $this->addSql('ALTER TABLE aap_equipment_group_worker DROP FOREIGN KEY FK_AAP_GW_WORKER');
        $this->addSql('ALTER TABLE aap_equipment_group_worker DROP FOREIGN KEY FK_AAP_GW_GROUP');
        $this->addSql('DROP TABLE aap_equipment_group_worker');
        $this->addSql('ALTER TABLE aap_equipment_group DROP FOREIGN KEY FK_AAP_EQ_GROUP_COMPANY');
        $this->addSql('DROP TABLE aap_equipment_group');
    }
}
