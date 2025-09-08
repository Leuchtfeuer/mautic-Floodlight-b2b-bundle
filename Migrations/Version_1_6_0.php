<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_6_0 extends AbstractMigration
{
    private string $table = 'f4b_company_domains';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            $table = $schema->getTable($this->concatPrefix($this->table));

            $needsTelcoColumn = !$table->hasColumn('is_telco');
            $needsUniqueConstraint = true;
            if ($table->hasColumn('domain')) {
                foreach ($table->getIndexes() as $index) {
                    if ($index->isUnique() && $index->getColumns() === ['domain']) {
                        $needsUniqueConstraint = false;
                        break;
                    }
                }
            }


            return $needsTelcoColumn || $needsUniqueConstraint;

        } catch (SchemaException $e) {
            return false;
        }
    }

    protected function up(): void
    {
        // Telco column only if not exists
        try {
            $this->addSql(
                "ALTER TABLE `{$this->concatPrefix($this->table)}` 
               ADD COLUMN `is_telco` TINYINT(1) NULL DEFAULT NULL "
            );
        } catch (\Exception $e) {
            // Column already exists - ignore
        }

        // Unique constraint only if not exists
        try {
            $this->addSql(
                "ALTER TABLE `{$this->concatPrefix($this->table)}` 
               ADD UNIQUE (`domain`)"
            );
        } catch (\Exception $e) {
            // Constraint already exists - ignore
        }
    }

}