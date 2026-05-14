<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260507194306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accounts (id INT AUTO_INCREMENT NOT NULL, balance DOUBLE PRECISION NOT NULL, currency_type VARCHAR(5) NOT NULL, status INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_CAC89EACA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE transactions (id INT AUTO_INCREMENT NOT NULL, amount DOUBLE PRECISION NOT NULL, status TINYTEXT NOT NULL, note LONGTEXT NOT NULL, receipt VARCHAR(255) NOT NULL, notifications_sent_at DATETIME DEFAULT NULL, from_account_id INT NOT NULL, to_account_id INT NOT NULL, INDEX IDX_EAA81A4CB0CF99BD (from_account_id), INDEX IDX_EAA81A4CBC58BDC7 (to_account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(25) NOT NULL, last_name VARCHAR(25) NOT NULL, email VARCHAR(50) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, contact_no VARCHAR(10) NOT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), UNIQUE INDEX UNIQ_1483A5E93F326A05 (contact_no), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE accounts ADD CONSTRAINT FK_CAC89EACA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CB0CF99BD FOREIGN KEY (from_account_id) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CBC58BDC7 FOREIGN KEY (to_account_id) REFERENCES accounts (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accounts DROP FOREIGN KEY FK_CAC89EACA76ED395');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CB0CF99BD');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CBC58BDC7');
        $this->addSql('DROP TABLE accounts');
        $this->addSql('DROP TABLE transactions');
        $this->addSql('DROP TABLE users');
    }
}
