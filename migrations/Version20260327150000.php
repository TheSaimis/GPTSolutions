<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'worker_risk many-to-many ir company_worker susiejimas su company_requisite';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE worker_risk (
            id INT AUTO_INCREMENT NOT NULL,
            worker_id INT NOT NULL,
            risk_factor_id INT NOT NULL,
            INDEX IDX_WR_WORKER (worker_id),
            INDEX IDX_WR_FACTOR (risk_factor_id),
            UNIQUE INDEX uniq_worker_risk (worker_id, risk_factor_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE worker_risk ADD CONSTRAINT FK_WR_WORKER FOREIGN KEY (worker_id) REFERENCES worker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE worker_risk ADD CONSTRAINT FK_WR_FACTOR FOREIGN KEY (risk_factor_id) REFERENCES health_risk_factor (id) ON DELETE CASCADE');

        // company_worker.company_id dabar rodo i company_requisite, ne i company.
        $this->addSql('ALTER TABLE company_worker DROP FOREIGN KEY FK_COMPANY_WORKER_COMPANY');
        $this->addSql('ALTER TABLE company_worker ADD CONSTRAINT FK_COMPANY_WORKER_COMPANY_REQ FOREIGN KEY (company_id) REFERENCES company_requisite (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_worker DROP FOREIGN KEY FK_COMPANY_WORKER_COMPANY_REQ');
        $this->addSql('ALTER TABLE company_worker ADD CONSTRAINT FK_COMPANY_WORKER_COMPANY FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE worker_risk DROP FOREIGN KEY FK_WR_WORKER');
        $this->addSql('ALTER TABLE worker_risk DROP FOREIGN KEY FK_WR_FACTOR');
        $this->addSql('DROP TABLE worker_risk');
    }
}

