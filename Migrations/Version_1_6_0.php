<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_6_0 extends AbstractMigration
{
    private string $table                                   = 'f4b_company_domains';
    private bool $needsTelcoColumn                          = false;

    protected function isApplicable(Schema $schema): bool
    {
            $table = $schema->getTable($this->concatPrefix($this->table));
            $this->needsTelcoColumn = !$table->hasColumn('is_telco');

            return true;
    }

    protected function up(): void
    {
        $tableName = $this->concatPrefix($this->table);

        if ($this->needsTelcoColumn) {
            try {
                $this->addSql(
                    "ALTER TABLE `{$tableName}` 
                   ADD COLUMN `is_telco` TINYINT(1) NULL DEFAULT NULL "
                );
            } catch (\Exception $e) {
            }
        }

        $this->addSql("SET FOREIGN_KEY_CHECKS = 0");

        $this->addSql("DELETE FROM `{$tableName}` WHERE domain IS NULL OR domain = ''");

        $this->addSql("ALTER TABLE `{$tableName}` CHANGE COLUMN `domain` `domain` VARCHAR(255) NOT NULL");
        $this->addSql("ALTER TABLE `{$tableName}` ADD CONSTRAINT `chk_domain_not_empty` CHECK (domain != '' AND TRIM(domain) != '' AND domain IS NOT NULL)");

        $this->addSql("ALTER TABLE `{$tableName}` DROP PRIMARY KEY");
        $this->addSql("ALTER TABLE `{$tableName}` ADD PRIMARY KEY (`domain`)");

        //Re-add the foreign key constraint that was lost
        $companiesTable = $this->concatPrefix('companies');
        $this->addSql("ALTER TABLE `{$tableName}` ADD CONSTRAINT `FK_{$this->table}_company_id` FOREIGN KEY (`company_id`) REFERENCES `{$companiesTable}` (`id`) ON DELETE CASCADE");

        $this->addSql("SET FOREIGN_KEY_CHECKS = 1");
        }
}
