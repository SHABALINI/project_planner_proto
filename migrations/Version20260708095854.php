<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260708095854 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subtask (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, task_id INT DEFAULT NULL, INDEX IDX_8BCBA9AE8DB60186 (task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, priority VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, area_id INT NOT NULL, INDEX IDX_527EDB25BD0F409C (area_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE subtask ADD CONSTRAINT FK_8BCBA9AE8DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25BD0F409C FOREIGN KEY (area_id) REFERENCES area (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subtask DROP FOREIGN KEY FK_8BCBA9AE8DB60186');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25BD0F409C');
        $this->addSql('DROP TABLE subtask');
        $this->addSql('DROP TABLE task');
    }
}
