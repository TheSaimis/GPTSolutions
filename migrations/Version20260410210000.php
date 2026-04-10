<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Įmonės + darbuotojo tipo + priemonės priskyrimai (AAP dokumentai)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE company_worker_equipment (
            id INT AUTO_INCREMENT NOT NULL,
            company_id INT NOT NULL,
            worker_id INT NOT NULL,
            equipment_id INT NOT NULL,
            UNIQUE INDEX uniq_company_worker_equipment (company_id, worker_id, equipment_id),
            INDEX IDX_CWE_COMPANY (company_id),
            INDEX IDX_CWE_WORKER (worker_id),
            INDEX IDX_CWE_EQUIPMENT (equipment_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE company_worker_equipment ADD CONSTRAINT FK_CWE_COMPANY FOREIGN KEY (company_id) REFERENCES company_requisite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_worker_equipment ADD CONSTRAINT FK_CWE_WORKER FOREIGN KEY (worker_id) REFERENCES worker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_worker_equipment ADD CONSTRAINT FK_CWE_EQUIPMENT FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE company_worker_equipment');
    }
}
