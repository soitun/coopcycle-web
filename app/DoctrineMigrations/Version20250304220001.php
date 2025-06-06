<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250304220001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pricing_rule ADD target TEXT NOT NULL DEFAULT \'DELIVERY\'');
        // convert all rules from a pricing rule set with strategy 'map' and WITHOUT 'map_all_tasks' option to LEGACY_TARGET_DYNAMIC rules
        $this->addSql('UPDATE pricing_rule pr
                SET target = \'LEGACY_TARGET_DYNAMIC\'
                FROM pricing_rule_set prs
                WHERE pr.rule_set_id = prs.id
                  AND prs.strategy = \'map\'
                  AND NOT (prs.options::jsonb ?? \'map_all_tasks\')');
        // convert all rules from a pricing rule set with strategy 'map' and 'map_all_tasks' option to TASK-based rules
        $this->addSql('UPDATE pricing_rule pr
                SET target = \'TASK\'
                FROM pricing_rule_set prs
                WHERE pr.rule_set_id = prs.id
                  AND prs.strategy = \'map\'
                  AND prs.options::jsonb ?? \'map_all_tasks\'');
        $this->addSql('UPDATE pricing_rule_set SET options = options::jsonb - \'map_all_tasks\' WHERE options::jsonb ?? \'map_all_tasks\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pricing_rule DROP target');
    }
}
