<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sequential material unlock flag to course_week and track student material views';
    }

    public function up(Schema $schema): void
    {
        $userTable = $schema->hasTable('app_user') ? 'app_user' : 'user';

        if ($schema->hasTable('course_week') && !$schema->getTable('course_week')->hasColumn('is_sequential_material_unlock_enabled')) {
            $this->addSql('ALTER TABLE course_week ADD is_sequential_material_unlock_enabled TINYINT(1) NOT NULL DEFAULT 0');
        }

        if (!$schema->hasTable('student_material_view')) {
            $this->addSql("CREATE TABLE student_material_view (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, material_id INT NOT NULL, viewed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_DA752301CB944F1A (student_id), INDEX IDX_DA752301E308AC6F (material_id), UNIQUE INDEX uniq_student_material_view (student_id, material_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
            $this->addSql(sprintf('ALTER TABLE student_material_view ADD CONSTRAINT FK_DA752301CB944F1A FOREIGN KEY (student_id) REFERENCES `%s` (id) ON DELETE CASCADE', $userTable));
            $this->addSql('ALTER TABLE student_material_view ADD CONSTRAINT FK_DA752301E308AC6F FOREIGN KEY (material_id) REFERENCES course_material (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('student_material_view')) {
            $this->addSql('ALTER TABLE student_material_view DROP FOREIGN KEY FK_DA752301CB944F1A');
            $this->addSql('ALTER TABLE student_material_view DROP FOREIGN KEY FK_DA752301E308AC6F');
            $this->addSql('DROP TABLE student_material_view');
        }

        if ($schema->hasTable('course_week') && $schema->getTable('course_week')->hasColumn('is_sequential_material_unlock_enabled')) {
            $this->addSql('ALTER TABLE course_week DROP is_sequential_material_unlock_enabled');
        }
    }
}
