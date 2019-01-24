<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190108195158 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user CHANGE register_date register_date DATETIME DEFAULT NULL, CHANGE birthday birthday DATE DEFAULT NULL, CHANGE gender gender VARCHAR(70) DEFAULT NULL, CHANGE sex sex VARCHAR(70) DEFAULT NULL, CHANGE gender_identity gender_identity VARCHAR(70) DEFAULT NULL, CHANGE register_ip register_ip VARCHAR(70) DEFAULT NULL, CHANGE last_ip last_ip VARCHAR(70) DEFAULT NULL, CHANGE last_login last_login DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user CHANGE register_date register_date DATETIME NOT NULL, CHANGE birthday birthday DATE NOT NULL, CHANGE gender gender VARCHAR(70) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE sex sex VARCHAR(70) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE gender_identity gender_identity VARCHAR(70) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE register_ip register_ip VARCHAR(70) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE last_ip last_ip VARCHAR(70) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE last_login last_login DATETIME NOT NULL');
    }
}
