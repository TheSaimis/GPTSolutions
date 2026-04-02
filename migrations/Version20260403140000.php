<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'company_types lentelė ir company_requisite.company_type_id FK; pašalinamas company_type tekstas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE company_types (id INT AUTO_INCREMENT NOT NULL, type_short VARCHAR(50) NOT NULL, type_short_en VARCHAR(50) DEFAULT NULL, type_short_ru VARCHAR(50) DEFAULT NULL, `type` VARCHAR(255) NOT NULL, type_en VARCHAR(255) DEFAULT NULL, type_ru VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_company_types_type_short (type_short), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO company_types (type_short, type_short_en, type_short_ru, `type`, type_en, type_ru) VALUES
            ('UAB', 'LLC', 'ООО', 'Uždaroji akcinė bendrovė', 'Private limited liability company', 'Закрытое акционерное общество'),
            ('AB', 'JSC', 'АО', 'Akcinė bendrovė', 'Joint stock company', 'Акционерное общество'),
            ('MB', 'LP', 'ТД', 'Mažoji bendrija', 'Small partnership', 'Малое товарищество'),
            ('VŠĮ', 'NPO', 'НКО', 'Viešoji įstaiga', 'Public institution', 'Общественная организация'),
            ('IĮ', 'IE', 'ИП', 'Individuali įmonė', 'Individual enterprise', 'Индивидуальное предприятие'),
            ('IND V.', 'IND ENT', 'ИП', 'Individuali veikla', 'Individual activity', 'Индивидуальная деятельность')");

        $this->addSql('ALTER TABLE company_requisite ADD company_type_id INT DEFAULT NULL');
        $this->addSql('UPDATE company_requisite cr INNER JOIN company_types ct ON TRIM(cr.company_type) = ct.type_short SET cr.company_type_id = ct.id WHERE cr.company_type IS NOT NULL AND TRIM(cr.company_type) <> \'\'');
        $this->addSql('UPDATE company_requisite cr INNER JOIN company_types ct ON TRIM(cr.company_type) = TRIM(TRAILING \'.\' FROM ct.type_short) SET cr.company_type_id = ct.id WHERE cr.company_type_id IS NULL AND cr.company_type IS NOT NULL AND TRIM(cr.company_type) <> \'\'');
        $this->addSql('UPDATE company_requisite cr INNER JOIN company_types ct ON CONCAT(TRIM(cr.company_type), \'.\') = ct.type_short SET cr.company_type_id = ct.id WHERE cr.company_type_id IS NULL AND cr.company_type IS NOT NULL AND TRIM(cr.company_type) <> \'\'');

        $this->addSql('ALTER TABLE company_requisite DROP company_type');
        $this->addSql('ALTER TABLE company_requisite ADD CONSTRAINT FK_company_requisite_company_type FOREIGN KEY (company_type_id) REFERENCES company_types (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_company_requisite_company_type ON company_requisite (company_type_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_requisite DROP FOREIGN KEY FK_company_requisite_company_type');
        $this->addSql('DROP INDEX IDX_company_requisite_company_type ON company_requisite');
        $this->addSql('ALTER TABLE company_requisite ADD company_type VARCHAR(100) DEFAULT NULL');
        $this->addSql('UPDATE company_requisite cr LEFT JOIN company_types ct ON cr.company_type_id = ct.id SET cr.company_type = ct.type_short WHERE ct.id IS NOT NULL');
        $this->addSql('ALTER TABLE company_requisite DROP company_type_id');
        $this->addSql('DROP TABLE company_types');
    }
}
