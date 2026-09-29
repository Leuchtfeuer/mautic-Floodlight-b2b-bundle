<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use Doctrine\DBAL\Connection;

/**
 * Aligns custom field definitions with Mautic 7's MAX_VARCHAR_LENGTH (191) constraint.
 *
 * - VARCHAR(255) text fields → VARCHAR(191), data truncated
 * - f4b_companydescription: text (VARCHAR) → textarea (LONGTEXT), index dropped
 * - f4b_companydescription_long: index dropped
 * - All text/select/timezone fields → char_length_limit = 191
 */
class FieldDefinitionMigrator
{
    /**
     * Text fields that shrink from VARCHAR(255) to VARCHAR(191).
     */
    private const VARCHAR_SHRINK_FIELDS = [
        'f4b_companyname_legal'        => 191,
        'f4b_companyname_alternatives' => 191,
        'f4b_companyindustry_linkedin' => 191,
    ];

    /**
     * All text/select/timezone fields whose char_length_limit should be 191.
     */
    private const LIMIT_TO_191_FIELDS = [
        'f4b_companyfloodlight_id',
        'f4b_companycrunchbase_handle',
        'f4b_companyname_legal',
        'f4b_companyname_alternatives',
        'f4b_companyindustry_linkedin',
        'f4b_companyindustry_icb_industrygroup',
        'f4b_companyindustry_icb_industry',
        'f4b_companyindustry_icb_subIndustry',
        'f4b_companyindustry_icb_sector',
        'f4b_companyemployees_range',
        'f4b_companyannual_revenue_range',
        'f4b_companytype',
        'f4b_companytimezone',
        'f4b_companyfacebook_handle',
        'f4b_companylinkedin_handle',
        'f4b_companytwitter_handle',
        'companylogo_filename',
    ];

    /**
     * Fields whose indexes must be dropped (LONGTEXT cannot have standard indexes).
     */
    private const DROP_INDEX_FIELDS = [
        'f4b_companydescription',
        'f4b_companydescription_long',
    ];

    private readonly string $companiesTable;
    private readonly string $leadFieldsTable;

    public function __construct(
        private readonly Connection $connection,
    ) {
        $prefix                = defined('MAUTIC_TABLE_PREFIX') && is_string(MAUTIC_TABLE_PREFIX) ? MAUTIC_TABLE_PREFIX : '';
        $this->companiesTable  = $prefix.'companies';
        $this->leadFieldsTable = $prefix.'lead_fields';
    }

    public function hasPendingMigration(): bool
    {
        return $this->hasVarcharFieldsToShrink()
            || $this->hasDescriptionToConvert()
            || $this->hasFieldsWithWrongLimit()
            || $this->hasIndexesToDrop();
    }

    /**
     * @return array{varchar_shrunk: int, rows_truncated: int, description_converted: bool, limits_updated: int, indexes_dropped: int}
     */
    public function migrate(): array
    {
        $stats = [
            'varchar_shrunk'        => 0,
            'rows_truncated'        => 0,
            'description_converted' => false,
            'limits_updated'        => 0,
            'indexes_dropped'       => 0,
        ];

        [$stats['varchar_shrunk'], $stats['rows_truncated']] = $this->truncateAndShrinkVarcharFields();
        $stats['indexes_dropped']                            = $this->dropIndexes();
        $stats['description_converted']                      = $this->convertDescriptionToTextarea();
        $stats['limits_updated']                             = $this->updateCharLengthLimits();

        return $stats;
    }

    private function hasVarcharFieldsToShrink(): bool
    {
        foreach (array_keys(self::VARCHAR_SHRINK_FIELDS) as $alias) {
            $length = $this->getVarcharLength($alias);
            if (null !== $length && $length > 191) {
                return true;
            }
        }

        return false;
    }

    private function hasDescriptionToConvert(): bool
    {
        $type = $this->getColumnDataType('f4b_companydescription');

        return null !== $type && 'varchar' === $type;
    }

    private function hasFieldsWithWrongLimit(): bool
    {
        $placeholders = implode(',', array_fill(0, count(self::LIMIT_TO_191_FIELDS), '?'));

        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM `{$this->leadFieldsTable}` WHERE `alias` IN ({$placeholders}) AND `object` = 'company' AND (`char_length_limit` IS NULL OR `char_length_limit` != 191)",
            self::LIMIT_TO_191_FIELDS
        ) > 0;
    }

    private function hasIndexesToDrop(): bool
    {
        foreach (self::DROP_INDEX_FIELDS as $alias) {
            if ($this->indexExists($alias.'_search')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{int, int} [fields altered, rows truncated]
     */
    private function truncateAndShrinkVarcharFields(): array
    {
        $fieldsAltered  = 0;
        $rowsTruncated  = 0;

        foreach (self::VARCHAR_SHRINK_FIELDS as $alias => $newLimit) {
            $currentLength = $this->getVarcharLength($alias);
            if (null === $currentLength || $currentLength <= $newLimit) {
                continue;
            }

            $rowsTruncated += $this->connection->executeStatement(
                "UPDATE `{$this->companiesTable}` SET `{$alias}` = LEFT(`{$alias}`, {$newLimit}) WHERE CHAR_LENGTH(`{$alias}`) > {$newLimit}"
            );

            $this->connection->executeStatement(
                "ALTER TABLE `{$this->companiesTable}` MODIFY COLUMN `{$alias}` VARCHAR({$newLimit}) DEFAULT NULL"
            );

            $this->connection->executeStatement(
                "UPDATE `{$this->leadFieldsTable}` SET `char_length_limit` = ? WHERE `alias` = ? AND `object` = 'company'",
                [$newLimit, $alias]
            );

            ++$fieldsAltered;
        }

        return [$fieldsAltered, $rowsTruncated];
    }

    private function convertDescriptionToTextarea(): bool
    {
        $type = $this->getColumnDataType('f4b_companydescription');
        if (null === $type || 'varchar' !== $type) {
            return false;
        }

        $this->connection->executeStatement(
            "ALTER TABLE `{$this->companiesTable}` MODIFY COLUMN `f4b_companydescription` LONGTEXT DEFAULT NULL"
        );

        $this->connection->executeStatement(
            "UPDATE `{$this->leadFieldsTable}` SET `type` = 'textarea', `char_length_limit` = 255 WHERE `alias` = 'f4b_companydescription' AND `object` = 'company'"
        );

        return true;
    }

    private function dropIndexes(): int
    {
        $dropped = 0;

        foreach (self::DROP_INDEX_FIELDS as $alias) {
            $indexName = $alias.'_search';
            if (!$this->indexExists($indexName)) {
                continue;
            }

            $this->connection->executeStatement(
                "DROP INDEX `{$indexName}` ON `{$this->companiesTable}`"
            );

            ++$dropped;
        }

        $placeholders = implode(',', array_fill(0, count(self::DROP_INDEX_FIELDS), '?'));
        $this->connection->executeStatement(
            "UPDATE `{$this->leadFieldsTable}` SET `is_index` = 0 WHERE `alias` IN ({$placeholders}) AND `object` = 'company' AND `is_index` = 1",
            self::DROP_INDEX_FIELDS
        );

        return $dropped;
    }

    private function updateCharLengthLimits(): int
    {
        $placeholders = implode(',', array_fill(0, count(self::LIMIT_TO_191_FIELDS), '?'));

        return $this->connection->executeStatement(
            "UPDATE `{$this->leadFieldsTable}` SET `char_length_limit` = 191 WHERE `alias` IN ({$placeholders}) AND `object` = 'company' AND (`char_length_limit` IS NULL OR `char_length_limit` != 191)",
            self::LIMIT_TO_191_FIELDS
        );
    }

    private function getVarcharLength(string $column): ?int
    {
        $result = $this->connection->fetchOne(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND DATA_TYPE = 'varchar'",
            [$this->companiesTable, $column]
        );

        return false !== $result ? (int) $result : null;
    }

    private function getColumnDataType(string $column): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->companiesTable, $column]
        );

        return false !== $result ? (string) $result : null;
    }

    private function indexExists(string $indexName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$this->companiesTable, $indexName]
        ) > 0;
    }
}
