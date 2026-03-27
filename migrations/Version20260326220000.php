<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sveikatos pazymos rizikos veiksniai: profiliai, bendri veiksniai, profilio papildomi veiksniai';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE health_risk_factor (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) NOT NULL,
            line_number INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE health_risk_profile (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(150) NOT NULL,
            checkup_term VARCHAR(120) NOT NULL,
            line_number INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE health_risk_common_factor (
            id INT AUTO_INCREMENT NOT NULL,
            factor_id INT NOT NULL,
            note VARCHAR(120) DEFAULT NULL,
            line_number INT NOT NULL DEFAULT 0,
            UNIQUE INDEX uniq_health_common_factor (factor_id),
            INDEX IDX_HRCF_FACTOR (factor_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE health_risk_profile_factor (
            id INT AUTO_INCREMENT NOT NULL,
            profile_id INT NOT NULL,
            factor_id INT NOT NULL,
            note VARCHAR(120) DEFAULT NULL,
            line_number INT NOT NULL DEFAULT 0,
            INDEX IDX_HRPF_PROFILE (profile_id),
            INDEX IDX_HRPF_FACTOR (factor_id),
            UNIQUE INDEX uniq_health_profile_factor (profile_id, factor_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE health_risk_common_factor ADD CONSTRAINT FK_HRCF_FACTOR FOREIGN KEY (factor_id) REFERENCES health_risk_factor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE health_risk_profile_factor ADD CONSTRAINT FK_HRPF_PROFILE FOREIGN KEY (profile_id) REFERENCES health_risk_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE health_risk_profile_factor ADD CONSTRAINT FK_HRPF_FACTOR FOREIGN KEY (factor_id) REFERENCES health_risk_factor (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE company_requisite ADD health_risk_profile_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE company_requisite ADD CONSTRAINT FK_COMPANY_HEALTH_PROFILE FOREIGN KEY (health_risk_profile_id) REFERENCES health_risk_profile (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_COMPANY_HEALTH_PROFILE ON company_requisite (health_risk_profile_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE health_risk_profile_factor DROP FOREIGN KEY FK_HRPF_PROFILE');
        $this->addSql('ALTER TABLE health_risk_profile_factor DROP FOREIGN KEY FK_HRPF_FACTOR');
        $this->addSql('ALTER TABLE health_risk_common_factor DROP FOREIGN KEY FK_HRCF_FACTOR');
        $this->addSql('ALTER TABLE company_requisite DROP FOREIGN KEY FK_COMPANY_HEALTH_PROFILE');

        $this->addSql('DROP TABLE health_risk_profile_factor');
        $this->addSql('DROP TABLE health_risk_common_factor');
        $this->addSql('DROP TABLE health_risk_profile');
        $this->addSql('DROP TABLE health_risk_factor');
        $this->addSql('DROP INDEX IDX_COMPANY_HEALTH_PROFILE ON company_requisite');
        $this->addSql('ALTER TABLE company_requisite DROP health_risk_profile_id');
    }
}
