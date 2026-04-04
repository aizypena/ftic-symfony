<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404002000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add display_order to course_material for per-week module sequencing.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course_material ADD display_order INT DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE course_material SET display_order = id');
        $this->addSql('CREATE INDEX IDX_COURSE_MATERIAL_WEEK_ORDER ON course_material (week_id, display_order)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_COURSE_MATERIAL_WEEK_ORDER ON course_material');
        $this->addSql('ALTER TABLE course_material DROP display_order');
    }
}
