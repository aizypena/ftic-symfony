<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316210305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE professor_event (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, professor_id INT NOT NULL, INDEX IDX_DA27F4FCE7E8D576 (professor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE professor_event ADD CONSTRAINT FK_DA27F4FCE7E8D576 FOREIGN KEY (professor_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE professor_event DROP FOREIGN KEY FK_DA27F4FCE7E8D576');
        $this->addSql('DROP TABLE professor_event');
    }
}
