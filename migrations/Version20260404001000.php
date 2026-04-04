<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add display_order to course_week for drag-and-drop module sequencing.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course_week ADD display_order INT DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE course_week SET display_order = week_number');
        $this->addSql('CREATE INDEX IDX_COURSE_WEEK_DISPLAY_ORDER ON course_week (display_order)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_COURSE_WEEK_DISPLAY_ORDER ON course_week');
        $this->addSql('ALTER TABLE course_week DROP display_order');
    }
}
