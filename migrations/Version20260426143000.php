<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make health risk factors unique by name and code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_health_risk_factor_name ON health_risk_factor (name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_health_risk_factor_code ON health_risk_factor (code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_health_risk_factor_name ON health_risk_factor');
        $this->addSql('DROP INDEX uniq_health_risk_factor_code ON health_risk_factor');
    }
}

