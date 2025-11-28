<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251128192616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agent_availability (id INT AUTO_INCREMENT NOT NULL, agent_id INT NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME NOT NULL, is_available TINYINT(1) NOT NULL, INDEX IDX_49FB2D743414710B (agent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE agent_skill (id INT AUTO_INCREMENT NOT NULL, agent_id INT NOT NULL, queue_id INT NOT NULL, efficiency_coefficient DOUBLE PRECISION NOT NULL, skill_level INT NOT NULL, is_primary TINYINT(1) NOT NULL, INDEX IDX_6793CC0F3414710B (agent_id), INDEX IDX_6793CC0F477B5BAE (queue_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE agent_availability ADD CONSTRAINT FK_49FB2D743414710B FOREIGN KEY (agent_id) REFERENCES agent (id)');
        $this->addSql('ALTER TABLE agent_skill ADD CONSTRAINT FK_6793CC0F3414710B FOREIGN KEY (agent_id) REFERENCES agent (id)');
        $this->addSql('ALTER TABLE agent_skill ADD CONSTRAINT FK_6793CC0F477B5BAE FOREIGN KEY (queue_id) REFERENCES queue (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE agent_availability DROP FOREIGN KEY FK_49FB2D743414710B');
        $this->addSql('ALTER TABLE agent_skill DROP FOREIGN KEY FK_6793CC0F3414710B');
        $this->addSql('ALTER TABLE agent_skill DROP FOREIGN KEY FK_6793CC0F477B5BAE');
        $this->addSql('DROP TABLE agent_availability');
        $this->addSql('DROP TABLE agent_skill');
    }
}
