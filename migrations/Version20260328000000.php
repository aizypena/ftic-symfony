<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename trainer domain objects to professor in database schema and data.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE app_user SET role = 'professor' WHERE role = 'trainer'");

        $this->addSql("SET @has_trainer_event := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trainer_event')");
        $this->addSql("SET @rename_event_sql := IF(@has_trainer_event > 0, 'RENAME TABLE trainer_event TO professor_event', 'SELECT 1')");
        $this->addSql('PREPARE stmt_rename_event FROM @rename_event_sql');
        $this->addSql('EXECUTE stmt_rename_event');
        $this->addSql('DEALLOCATE PREPARE stmt_rename_event');

        $this->addSql("SET @has_prof_event_trainer_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'professor_event' AND COLUMN_NAME = 'trainer_id')");
        $this->addSql("SET @rename_prof_event_col_sql := IF(@has_prof_event_trainer_col > 0, 'ALTER TABLE professor_event CHANGE trainer_id professor_id INT NOT NULL', 'SELECT 1')");
        $this->addSql('PREPARE stmt_prof_event_col FROM @rename_prof_event_col_sql');
        $this->addSql('EXECUTE stmt_prof_event_col');
        $this->addSql('DEALLOCATE PREPARE stmt_prof_event_col');

        $this->addSql("SET @has_course_trainer_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course' AND COLUMN_NAME = 'trainer_id')");
        $this->addSql("SET @rename_course_col_sql := IF(@has_course_trainer_col > 0, 'ALTER TABLE course CHANGE trainer_id professor_id INT DEFAULT NULL', 'SELECT 1')");
        $this->addSql('PREPARE stmt_course_col FROM @rename_course_col_sql');
        $this->addSql('EXECUTE stmt_course_col');
        $this->addSql('DEALLOCATE PREPARE stmt_course_col');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE app_user SET role = 'trainer' WHERE role = 'professor'");

        $this->addSql("SET @has_professor_event := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'professor_event')");
        $this->addSql("SET @rename_event_sql := IF(@has_professor_event > 0, 'RENAME TABLE professor_event TO trainer_event', 'SELECT 1')");
        $this->addSql('PREPARE stmt_rename_event FROM @rename_event_sql');
        $this->addSql('EXECUTE stmt_rename_event');
        $this->addSql('DEALLOCATE PREPARE stmt_rename_event');

        $this->addSql("SET @has_trainer_event_prof_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trainer_event' AND COLUMN_NAME = 'professor_id')");
        $this->addSql("SET @rename_trainer_event_col_sql := IF(@has_trainer_event_prof_col > 0, 'ALTER TABLE trainer_event CHANGE professor_id trainer_id INT NOT NULL', 'SELECT 1')");
        $this->addSql('PREPARE stmt_trainer_event_col FROM @rename_trainer_event_col_sql');
        $this->addSql('EXECUTE stmt_trainer_event_col');
        $this->addSql('DEALLOCATE PREPARE stmt_trainer_event_col');

        $this->addSql("SET @has_course_prof_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course' AND COLUMN_NAME = 'professor_id')");
        $this->addSql("SET @rename_course_col_sql := IF(@has_course_prof_col > 0, 'ALTER TABLE course CHANGE professor_id trainer_id INT DEFAULT NULL', 'SELECT 1')");
        $this->addSql('PREPARE stmt_course_col FROM @rename_course_col_sql');
        $this->addSql('EXECUTE stmt_course_col');
        $this->addSql('DEALLOCATE PREPARE stmt_course_col');
    }
}
