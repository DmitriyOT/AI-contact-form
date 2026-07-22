<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260722145435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ai_summary column to contacts table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contacts ADD ai_summary VARCHAR(255) DEFAULT NULL AFTER ai_category');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contacts DROP ai_summary');
    }
}
