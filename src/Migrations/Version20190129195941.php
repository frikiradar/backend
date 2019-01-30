<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190129195941 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user ADD lovegender JSON DEFAULT NULL, ADD minage INT DEFAULT NULL, ADD maxage INT DEFAULT NULL, ADD connection JSON DEFAULT NULL, DROP interesting, CHANGE register_date register_date DATETIME DEFAULT NULL, CHANGE birthday birthday DATE DEFAULT NULL, CHANGE gender gender VARCHAR(70) DEFAULT NULL, CHANGE register_ip register_ip VARCHAR(70) DEFAULT NULL, CHANGE last_ip last_ip VARCHAR(70) DEFAULT NULL, CHANGE last_login last_login DATETIME DEFAULT NULL, CHANGE latitude latitude VARCHAR(255) DEFAULT NULL, CHANGE relationship relationship VARCHAR(70) DEFAULT NULL, CHANGE longitude longitude VARCHAR(255) DEFAULT NULL, CHANGE orientation orientation VARCHAR(70) DEFAULT NULL, CHANGE pronoun pronoun VARCHAR(70) DEFAULT NULL, CHANGE status status VARCHAR(70) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user ADD interesting LONGTEXT DEFAULT NULL COLLATE utf8mb4_bin, DROP lovegender, DROP minage, DROP maxage, DROP connection, CHANGE register_date register_date DATETIME DEFAULT \'NULL\', CHANGE birthday birthday DATE DEFAULT \'NULL\', CHANGE gender gender VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE orientation orientation VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE relationship relationship VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE pronoun pronoun VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE status status VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE register_ip register_ip VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE last_ip last_ip VARCHAR(70) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE last_login last_login DATETIME DEFAULT \'NULL\', CHANGE latitude latitude VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE longitude longitude VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci');
    }
}
