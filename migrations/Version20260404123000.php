<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair migration: ensure course_material.prerequisite_material_id exists with index and FK';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('course_material')) {
            return;
        }

        $table = $schema->getTable('course_material');

        if (!$table->hasColumn('prerequisite_material_id')) {
            $this->addSql('ALTER TABLE course_material ADD prerequisite_material_id INT DEFAULT NULL');
        }

        if (!$table->hasIndex('idx_course_material_prereq')) {
            $this->addSql('CREATE INDEX idx_course_material_prereq ON course_material (prerequisite_material_id)');
        }

        if (!$table->hasForeignKey('fk_course_material_prereq')) {
            $this->addSql('ALTER TABLE course_material ADD CONSTRAINT fk_course_material_prereq FOREIGN KEY (prerequisite_material_id) REFERENCES course_material (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('course_material')) {
            return;
        }

        $table = $schema->getTable('course_material');

        if ($table->hasForeignKey('fk_course_material_prereq')) {
            $this->addSql('ALTER TABLE course_material DROP FOREIGN KEY fk_course_material_prereq');
        }

        if ($table->hasIndex('idx_course_material_prereq')) {
            $this->addSql('DROP INDEX idx_course_material_prereq ON course_material');
        }

        if ($table->hasColumn('prerequisite_material_id')) {
            $this->addSql('ALTER TABLE course_material DROP prerequisite_material_id');
        }
    }
}
