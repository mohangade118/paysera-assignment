<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507204300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore transactions.notifications_sent_at for idempotent email notifications';
    }

    public function up(Schema $schema): void
    {
        if ($schema->getTable('transactions')->hasColumn('notifications_sent_at')) {
            return;
        }

        $this->addSql('ALTER TABLE transactions ADD notifications_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->getTable('transactions')->hasColumn('notifications_sent_at')) {
            return;
        }

        $this->addSql('ALTER TABLE transactions DROP notifications_sent_at');
    }
}
