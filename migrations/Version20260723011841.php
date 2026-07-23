<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723011841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ai_priority and ai_draft_reply columns to contacts table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contacts ADD ai_priority VARCHAR(20) DEFAULT NULL AFTER ai_summary, ADD ai_draft_reply TEXT DEFAULT NULL AFTER ai_priority');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contacts DROP ai_priority, DROP ai_draft_reply');
    }
}
