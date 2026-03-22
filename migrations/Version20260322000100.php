<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add academic_term table and link courses to an academic term.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE academic_term (id INT AUTO_INCREMENT NOT NULL, school_year VARCHAR(20) NOT NULL, term_label VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE course ADD term_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_169E6FB1EBAF6CC ON course (term_id)');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB1EBAF6CC FOREIGN KEY (term_id) REFERENCES academic_term (id)');
        $this->addSql("INSERT INTO academic_term (school_year, term_label, start_date, end_date, is_active, created_at, updated_at) VALUES ('Default', 'Initial Term', '2000-01-01', '2099-12-31', 1, NOW(), NOW())");
        $this->addSql('UPDATE course SET term_id = (SELECT id FROM academic_term ORDER BY id ASC LIMIT 1) WHERE term_id IS NULL');
        $this->addSql('ALTER TABLE course MODIFY term_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course DROP FOREIGN KEY FK_169E6FB1EBAF6CC');
        $this->addSql('DROP INDEX IDX_169E6FB1EBAF6CC ON course');
        $this->addSql('ALTER TABLE course DROP term_id');
        $this->addSql('DROP TABLE academic_term');
    }
}
