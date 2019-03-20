<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190320095741 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, to_user_id INT NOT NULL, from_user_id INT DEFAULT NULL, time_creation DATETIME NOT NULL, title VARCHAR(255) NOT NULL, text VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, type VARCHAR(70) NOT NULL, viewed TINYINT(1) NOT NULL, INDEX IDX_BF5476CA29F6EE60 (to_user_id), INDEX IDX_BF5476CA2130303A (from_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA29F6EE60 FOREIGN KEY (to_user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA2130303A FOREIGN KEY (from_user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE notification');
    }
}
