<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_6_0 extends AbstractMigration
{
    private string $table                                         = 'f4b_company_domains';
    private bool $needsTelcoColumn                                = false;
    private bool $companyIsPartOfCombinedPrimaryKey               = false;
    private bool $domainNeedsUniqueIndex                          = false;
    private bool $companyNeedsIdIndex                             = false;

    protected function isApplicable(Schema $schema): bool
    {
        $table                  = $schema->getTable($this->concatPrefix($this->table));
        $this->needsTelcoColumn = !$table->hasColumn('is_telco');

        $primaryKey = $table->getPrimaryKey();
        if ($primaryKey) {
            $this->companyIsPartOfCombinedPrimaryKey = in_array('company_id', $primaryKey->getColumns());
        }

        $this->domainNeedsUniqueIndex     = !$table->hasIndex('unique_domain');
        $this->companyNeedsIdIndex        = !$table->hasIndex('idx_company_id');

        return true;
    }

    protected function up(): void
    {
        $tableName = $this->concatPrefix($this->table);

        if ($this->needsTelcoColumn) {
            $this->addSql(
                "ALTER TABLE `{$tableName}`
               ADD COLUMN `is_telco` TINYINT(1) NULL DEFAULT NULL "
            );
        }

        if ($this->companyIsPartOfCombinedPrimaryKey) {
            try {
                $this->addSql('SET FOREIGN_KEY_CHECKS = 0');

                $this->addSql("DELETE FROM `{$tableName}` WHERE domain IS NULL OR domain = ''");

                // Delete duplicates, keep only the entry with the smallest company_id per domain
                $this->addSql("
            DELETE t1 FROM `{$tableName}` t1
            INNER JOIN `{$tableName}` t2
            WHERE t1.domain = t2.domain
            AND t1.company_id > t2.company_id
        ");

                $this->addSql("ALTER TABLE `{$tableName}` CHANGE COLUMN `domain` `domain` VARCHAR(255) NOT NULL");

                if ($this->domainNeedsUniqueIndex) {
                    $this->addSql("ALTER TABLE `{$tableName}` ADD UNIQUE KEY `unique_domain` (`domain`)");
                }

                // Add index on company_id BEFORE dropping primary key (FK requires an index)
                if ($this->companyNeedsIdIndex) {
                    $this->addSql("ALTER TABLE `{$tableName}` ADD INDEX `idx_company_id` (`company_id`)");
                }

                $this->addSql("ALTER TABLE `{$tableName}` DROP PRIMARY KEY");
                $this->addSql("ALTER TABLE `{$tableName}` ADD PRIMARY KEY (`domain`)");
            } finally {
                $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
            }
        }
    }
}
