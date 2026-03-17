<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260317143625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE student_submission (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, uploaded_at DATETIME NOT NULL, student_id INT NOT NULL, course_week_id INT NOT NULL, INDEX IDX_36DAB712CB944F1A (student_id), INDEX IDX_36DAB712C0AC3EC3 (course_week_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE student_submission ADD CONSTRAINT FK_36DAB712CB944F1A FOREIGN KEY (student_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE student_submission ADD CONSTRAINT FK_36DAB712C0AC3EC3 FOREIGN KEY (course_week_id) REFERENCES course_week (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student_submission DROP FOREIGN KEY FK_36DAB712CB944F1A');
        $this->addSql('ALTER TABLE student_submission DROP FOREIGN KEY FK_36DAB712C0AC3EC3');
        $this->addSql('DROP TABLE student_submission');
    }
}
