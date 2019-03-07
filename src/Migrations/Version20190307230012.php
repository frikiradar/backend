<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190307230012 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL, CHANGE register_date register_date DATETIME DEFAULT NULL, CHANGE birthday birthday DATE DEFAULT NULL, CHANGE gender gender VARCHAR(70) DEFAULT NULL, CHANGE orientation orientation VARCHAR(70) DEFAULT NULL, CHANGE relationship relationship VARCHAR(70) DEFAULT NULL, CHANGE pronoun pronoun VARCHAR(70) DEFAULT NULL, CHANGE status status VARCHAR(70) DEFAULT NULL, CHANGE register_ip register_ip VARCHAR(70) DEFAULT NULL, CHANGE last_ip last_ip VARCHAR(70) DEFAULT NULL, CHANGE last_login last_login DATETIME DEFAULT NULL, CHANGE lovegender lovegender JSON DEFAULT NULL, CHANGE minage minage INT DEFAULT NULL, CHANGE maxage maxage INT DEFAULT NULL, CHANGE connection connection JSON DEFAULT NULL, CHANGE coordinates coordinates POINT DEFAULT NULL COMMENT \'(DC2Type:point)\', CHANGE location location VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user CHANGE roles roles LONGTEXT NOT NULL COLLATE utf8mb4_bin, CHANGE register_date register_date DATETIME DEFAULT \'NULL\', CHANGE gender gender VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE orientation orientation VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE relationship relationship VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE pronoun pronoun VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE status status VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE register_ip register_ip VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE last_ip last_ip VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE last_login last_login DATETIME DEFAULT \'NULL\', CHANGE lovegender lovegender LONGTEXT DEFAULT NULL COLLATE utf8mb4_bin, CHANGE minage minage INT DEFAULT NULL, CHANGE maxage maxage INT DEFAULT NULL, CHANGE connection connection LONGTEXT DEFAULT NULL COLLATE utf8mb4_bin, CHANGE coordinates coordinates POINT DEFAULT \'NULL\' COMMENT \'(DC2Type:point)\', CHANGE birthday birthday DATE DEFAULT \'NULL\', CHANGE location location VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci');
    }
}
