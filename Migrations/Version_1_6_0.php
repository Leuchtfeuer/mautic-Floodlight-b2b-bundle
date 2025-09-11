<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_6_0 extends AbstractMigration
{
    private string $table                                  = 'f4b_company_domains';
    private bool $isTelcoColumnExistsInCompanyDomainTable  = false;
    private bool $domainIsUniqueColumnInCompanyDomainTable = false;

    protected function isApplicable(Schema $schema): bool
    {
        try {
            $table = $schema->getTable($this->concatPrefix($this->table));

            $this->isTelcoColumnExistsInCompanyDomainTable = !$table->hasColumn('is_telco');
            if ($table->hasColumn('domain')) {
                foreach ($table->getIndexes() as $index) {
                    if ($index->isUnique() && $index->getColumns() === ['domain']) {
                        $this->domainIsUniqueColumnInCompanyDomainTable = true;
                        break;
                    }
                }
            }

            return $this->isTelcoColumnExistsInCompanyDomainTable || $this->domainIsUniqueColumnInCompanyDomainTable;
        } catch (SchemaException $e) {
            return false;
        }
    }

    protected function up(): void
    {
        // Telco column only if not exists
        try {
            if (!$this->isTelcoColumnExistsInCompanyDomainTable) {
                $this->addSql(
                    "ALTER TABLE `{$this->concatPrefix($this->table)}` 
               ADD COLUMN `is_telco` TINYINT(1) NULL DEFAULT NULL "
                );
            }
        } catch (\Exception $e) {
            // Column already exists - ignore
        }

        // Unique constraint only if not exists
        try {
            if (!$this->domainIsUniqueColumnInCompanyDomainTable) {
                $this->addSql(
                    "ALTER TABLE `{$this->concatPrefix($this->table)}` 
               ADD UNIQUE (`domain`)"
                );
            }
        } catch (\Exception $e) {
            // Constraint already exists - ignore
        }
    }
}
