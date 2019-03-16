<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190316160155 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE chat (id INT AUTO_INCREMENT NOT NULL, fromuser_id INT DEFAULT NULL, touser_id INT DEFAULT NULL, text LONGTEXT NOT NULL, time_creation DATETIME NOT NULL, time_read DATETIME NOT NULL, INDEX IDX_659DF2AAD36C4FC6 (fromuser_id), INDEX IDX_659DF2AA788F388B (touser_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE chat ADD CONSTRAINT FK_659DF2AAD36C4FC6 FOREIGN KEY (fromuser_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE chat ADD CONSTRAINT FK_659DF2AA788F388B FOREIGN KEY (touser_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL, CHANGE register_date register_date DATETIME DEFAULT NULL, CHANGE birthday birthday DATE DEFAULT NULL, CHANGE gender gender VARCHAR(70) DEFAULT NULL, CHANGE orientation orientation VARCHAR(70) DEFAULT NULL, CHANGE relationship relationship VARCHAR(70) DEFAULT NULL, CHANGE pronoun pronoun VARCHAR(70) DEFAULT NULL, CHANGE status status VARCHAR(70) DEFAULT NULL, CHANGE register_ip register_ip VARCHAR(70) DEFAULT NULL, CHANGE last_ip last_ip VARCHAR(70) DEFAULT NULL, CHANGE last_login last_login DATETIME DEFAULT NULL, CHANGE lovegender lovegender JSON DEFAULT NULL, CHANGE minage minage INT DEFAULT NULL, CHANGE maxage maxage INT DEFAULT NULL, CHANGE connection connection JSON DEFAULT NULL, CHANGE coordinates coordinates POINT DEFAULT NULL COMMENT \'(DC2Type:point)\', CHANGE location location VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE chat');
        $this->addSql('ALTER TABLE user CHANGE roles roles LONGTEXT NOT NULL COLLATE utf8mb4_bin, CHANGE register_date register_date DATETIME DEFAULT \'NULL\', CHANGE gender gender VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE orientation orientation VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE relationship relationship VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE pronoun pronoun VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE status status VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE register_ip register_ip VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE last_ip last_ip VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE last_login last_login DATETIME DEFAULT \'NULL\', CHANGE lovegender lovegender LONGTEXT DEFAULT NULL COLLATE utf8mb4_bin, CHANGE minage minage INT DEFAULT NULL, CHANGE maxage maxage INT DEFAULT NULL, CHANGE connection connection LONGTEXT DEFAULT NULL COLLATE utf8mb4_bin, CHANGE coordinates coordinates POINT DEFAULT \'NULL\' COMMENT \'(DC2Type:point)\', CHANGE birthday birthday DATE DEFAULT \'NULL\', CHANGE location location VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci');
    }
}
