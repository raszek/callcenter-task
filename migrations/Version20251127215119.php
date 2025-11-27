<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251127215119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE historical_call_data (id INT AUTO_INCREMENT NOT NULL, queue_id INT NOT NULL, datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', call_count INT NOT NULL, average_handle_time_seconds DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_690924E0477B5BAE (queue_id), INDEX idx_datetime (datetime), INDEX idx_queue_datetime (queue_id, datetime), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE historical_call_data ADD CONSTRAINT FK_690924E0477B5BAE FOREIGN KEY (queue_id) REFERENCES queue (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE historical_call_data DROP FOREIGN KEY FK_690924E0477B5BAE');
        $this->addSql('DROP TABLE historical_call_data');
    }
}
