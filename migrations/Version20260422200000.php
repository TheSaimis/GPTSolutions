<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AAP priemonių laukai EN/RU; AAP Word šablonai pagal kalbą (lt/en/ru)';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        $equipmentCols = $sm->listTableColumns('equipment');
        if (! isset($equipmentCols['name_en'])) {
            $this->addSql('ALTER TABLE equipment ADD name_en VARCHAR(255) DEFAULT NULL, ADD name_ru VARCHAR(255) DEFAULT NULL, ADD expiration_date_en VARCHAR(120) DEFAULT NULL, ADD expiration_date_ru VARCHAR(120) DEFAULT NULL');
        }

        $tplCols = $sm->listTableColumns('aap_equipment_word_template');
        if (! isset($tplCols['template_locale'])) {
            $this->addSql("ALTER TABLE aap_equipment_word_template ADD template_locale VARCHAR(8) NOT NULL DEFAULT 'lt'");
            $this->addSql("UPDATE aap_equipment_word_template SET template_locale = 'lt' WHERE template_locale = '' OR template_locale IS NULL");
        }

        $tplIndexes = $sm->listTableIndexes('aap_equipment_word_template');
        if (isset($tplIndexes['uniq_aap_template_kind'])) {
            $this->addSql('ALTER TABLE aap_equipment_word_template DROP INDEX uniq_aap_template_kind');
        }

        if (! isset($tplIndexes['uniq_aap_template_kind_locale'])) {
            $this->addSql('CREATE UNIQUE INDEX uniq_aap_template_kind_locale ON aap_equipment_word_template (template_kind, template_locale)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE aap_equipment_word_template DROP INDEX uniq_aap_template_kind_locale');
        $this->addSql('CREATE UNIQUE INDEX uniq_aap_template_kind ON aap_equipment_word_template (template_kind)');
        $this->addSql('ALTER TABLE aap_equipment_word_template DROP COLUMN template_locale');

        $this->addSql('ALTER TABLE equipment DROP name_en, DROP name_ru, DROP expiration_date_en, DROP expiration_date_ru');
    }
}
