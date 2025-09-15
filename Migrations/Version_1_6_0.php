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
    private bool $domainColumnExists      = false;
    private bool $companyIsInPrimaryKeyInCompanyDomainTable = false;

    protected function isApplicable(Schema $schema): bool
    {
        try {
            $table = $schema->getTable($this->concatPrefix($this->table));

            // Check if telco column is missing
            $this->needsTelcoColumn = !$table->hasColumn('is_telco');

            if ($table->hasColumn('domain')) {
                // Check if domain is nullable
                $this->domainColumnExists = true;
            }

            // Check if company_id is part of the primary key
            $primaryKey = $table->getPrimaryKey();
            if ($primaryKey) {
                $this->companyIsInPrimaryKeyInCompanyDomainTable = in_array('company_id', $primaryKey->getColumns());
            }

            return $this->needsTelcoColumn
                || $this->domainColumnExists
                || $this->companyIsInPrimaryKeyInCompanyDomainTable;
        } catch (SchemaException $e) {
            return false;
        }
    }

    protected function up(): void
    {
        $tableName = $this->concatPrefix($this->table);

        // Telco column only if not exists
        if ($this->needsTelcoColumn) {
            try {
                $this->addSql(
                    "ALTER TABLE `{$tableName}` 
                   ADD COLUMN `is_telco` TINYINT(1) NULL DEFAULT NULL "
                );
            } catch (\Exception $e) {
            }
        }

// Domain NOT NULL and Primary Key changes
        if ($this->domainColumnExists || $this->companyIsInPrimaryKeyInCompanyDomainTable) {
            // 0. Disable foreign key checks temporarily
            $this->addSql("SET FOREIGN_KEY_CHECKS = 0");

            // 1. Delete NULL values first
            $this->addSql("DELETE FROM `{$tableName}` WHERE domain IS NULL OR domain = ''");

            // 2. Set domain to NOT NULL before changing primary key
            if ($this->domainColumnExists) {
                $this->addSql("ALTER TABLE `{$tableName}` CHANGE COLUMN `domain` `domain` VARCHAR(255) NOT NULL");
                $this->addSql("ALTER TABLE `{$tableName}` ADD CONSTRAINT `chk_domain_not_empty` CHECK (domain != '' AND TRIM(domain) != '' AND domain IS NOT NULL)");
            }

            // 3. Drop old primary key if company is in it
            if ($this->companyIsInPrimaryKeyInCompanyDomainTable) {
                $this->addSql("ALTER TABLE `{$tableName}` DROP PRIMARY KEY");
            }

            // 4. Add new primary key on domain only
            if ($this->companyIsInPrimaryKeyInCompanyDomainTable) {
                $this->addSql("ALTER TABLE `{$tableName}` ADD PRIMARY KEY (`domain`)");
            }

            // 5. Re-add the foreign key constraint that was lost
            $companiesTable = $this->concatPrefix('companies');
            $this->addSql("ALTER TABLE `{$tableName}` ADD CONSTRAINT `FK_{$this->table}_company_id` FOREIGN KEY (`company_id`) REFERENCES `{$companiesTable}` (`id`) ON DELETE CASCADE");

            // 6. Re-enable foreign key checks
            $this->addSql("SET FOREIGN_KEY_CHECKS = 1");
        }
    }
}
