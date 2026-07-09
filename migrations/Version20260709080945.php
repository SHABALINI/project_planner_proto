<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260709080945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_member (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(20) NOT NULL, project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_67401132166D1F9C (project_id), INDEX IDX_67401132A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_member_area (project_member_id INT NOT NULL, area_id INT NOT NULL, INDEX IDX_E3D3E6CF64AB9629 (project_member_id), INDEX IDX_E3D3E6CFBD0F409C (area_id), PRIMARY KEY (project_member_id, area_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_member_task (project_member_id INT NOT NULL, task_id INT NOT NULL, INDEX IDX_6639008264AB9629 (project_member_id), INDEX IDX_663900828DB60186 (task_id), PRIMARY KEY (project_member_id, task_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_member_subtask (project_member_id INT NOT NULL, subtask_id INT NOT NULL, INDEX IDX_DD882A9864AB9629 (project_member_id), INDEX IDX_DD882A98C6D4A949 (subtask_id), PRIMARY KEY (project_member_id, subtask_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_member ADD CONSTRAINT FK_67401132166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_member ADD CONSTRAINT FK_67401132A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_member_area ADD CONSTRAINT FK_E3D3E6CF64AB9629 FOREIGN KEY (project_member_id) REFERENCES project_member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_member_area ADD CONSTRAINT FK_E3D3E6CFBD0F409C FOREIGN KEY (area_id) REFERENCES area (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_member_task ADD CONSTRAINT FK_6639008264AB9629 FOREIGN KEY (project_member_id) REFERENCES project_member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_member_task ADD CONSTRAINT FK_663900828DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_member_subtask ADD CONSTRAINT FK_DD882A9864AB9629 FOREIGN KEY (project_member_id) REFERENCES project_member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_member_subtask ADD CONSTRAINT FK_DD882A98C6D4A949 FOREIGN KEY (subtask_id) REFERENCES subtask (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_member DROP FOREIGN KEY FK_67401132166D1F9C');
        $this->addSql('ALTER TABLE project_member DROP FOREIGN KEY FK_67401132A76ED395');
        $this->addSql('ALTER TABLE project_member_area DROP FOREIGN KEY FK_E3D3E6CF64AB9629');
        $this->addSql('ALTER TABLE project_member_area DROP FOREIGN KEY FK_E3D3E6CFBD0F409C');
        $this->addSql('ALTER TABLE project_member_task DROP FOREIGN KEY FK_6639008264AB9629');
        $this->addSql('ALTER TABLE project_member_task DROP FOREIGN KEY FK_663900828DB60186');
        $this->addSql('ALTER TABLE project_member_subtask DROP FOREIGN KEY FK_DD882A9864AB9629');
        $this->addSql('ALTER TABLE project_member_subtask DROP FOREIGN KEY FK_DD882A98C6D4A949');
        $this->addSql('DROP TABLE project_member');
        $this->addSql('DROP TABLE project_member_area');
        $this->addSql('DROP TABLE project_member_task');
        $this->addSql('DROP TABLE project_member_subtask');
    }
}
