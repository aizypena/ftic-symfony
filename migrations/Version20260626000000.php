<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add the media type column to course_material table.
 */
final class Version20260626000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type column to course_material table to support video lessons.';
    }

    public function up(Schema $schema): void
    {
        // This line cleanly alters the table configuration inside XAMPP/MySQL
        $this->addSql('ALTER TABLE course_material ADD type VARCHAR(20) DEFAULT "pdf" NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Keeps the system reversible
        $this->addSql('ALTER TABLE course_material DROP type');
    }
}
