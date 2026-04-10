<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AAP Word šablonų turinys DB (sarasas / korteles)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE aap_equipment_word_template (
            id INT AUTO_INCREMENT NOT NULL,
            template_kind VARCHAR(20) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            content MEDIUMBLOB NOT NULL,
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_aap_template_kind (template_kind),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE aap_equipment_word_template');
    }
}
