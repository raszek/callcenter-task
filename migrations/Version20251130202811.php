<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251130202811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE agent_availability DROP FOREIGN KEY FK_49FB2D743414710B');
        $this->addSql('ALTER TABLE agent_availability CHANGE start_time start_time DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE end_time end_time DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE agent_availability ADD CONSTRAINT FK_49FB2D743414710B FOREIGN KEY (agent_id) REFERENCES agent (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE agent_availability DROP FOREIGN KEY FK_49FB2D743414710B');
        $this->addSql('ALTER TABLE agent_availability CHANGE start_time start_time DATETIME NOT NULL, CHANGE end_time end_time DATETIME NOT NULL');
        $this->addSql('ALTER TABLE agent_availability ADD CONSTRAINT FK_49FB2D743414710B FOREIGN KEY (agent_id) REFERENCES agent (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
