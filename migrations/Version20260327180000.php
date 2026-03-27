<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AAP: equipment ir worker_item many-to-many (su expiration_date string)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE equipment (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            expiration_date VARCHAR(120) NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE worker_item (
            id INT AUTO_INCREMENT NOT NULL,
            worker_id INT NOT NULL,
            equipment_id INT NOT NULL,
            INDEX IDX_WORKER_ITEM_WORKER (worker_id),
            INDEX IDX_WORKER_ITEM_EQUIPMENT (equipment_id),
            UNIQUE INDEX uniq_worker_item (worker_id, equipment_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE worker_item ADD CONSTRAINT FK_WORKER_ITEM_WORKER FOREIGN KEY (worker_id) REFERENCES worker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE worker_item ADD CONSTRAINT FK_WORKER_ITEM_EQUIPMENT FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worker_item DROP FOREIGN KEY FK_WORKER_ITEM_WORKER');
        $this->addSql('ALTER TABLE worker_item DROP FOREIGN KEY FK_WORKER_ITEM_EQUIPMENT');
        $this->addSql('DROP TABLE worker_item');
        $this->addSql('DROP TABLE equipment');
    }
}

