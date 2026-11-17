<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Helper\Serializer;

class CompanyDescriptionFieldMigrator
{
    public const LIMIT = 191;

    private const CORE_ALIAS   = 'companydescription';
    private const LEGACY_ALIAS = 'f4b_companydescription';

    private readonly string $companiesTable;
    private readonly string $leadFieldsTable;
    private readonly string $leadListsTable;

    public function __construct(
        private readonly Connection $connection,
    ) {
        $prefix                = defined('MAUTIC_TABLE_PREFIX') && is_string(MAUTIC_TABLE_PREFIX) ? MAUTIC_TABLE_PREFIX : '';
        $this->companiesTable  = $prefix.'companies';
        $this->leadFieldsTable = $prefix.'lead_fields';
        $this->leadListsTable  = $prefix.'lead_lists';
    }

    public function hasPendingMigration(): bool
    {
        return $this->hasLegacyColumn()
            || $this->hasLegacyField()
            || [] !== $this->fetchSegmentsWithLegacyFilter()
            || self::LIMIT !== $this->getCoreLimit();
    }

    public function migrate(): void
    {
        $core   = self::CORE_ALIAS;
        $legacy = self::LEGACY_ALIAS;
        $limit  = self::LIMIT;

        $this->rewriteSegmentFilters();

        if ($this->hasLegacyColumn()) {
            $this->connection->executeStatement(
                "ALTER TABLE `{$this->companiesTable}` DROP COLUMN `{$legacy}`"
            );
        }

        $this->connection->executeStatement(
            "DELETE FROM `{$this->leadFieldsTable}` WHERE `alias` = ? AND `object` = 'company'",
            [$legacy]
        );

        $this->connection->executeStatement(
            "UPDATE `{$this->companiesTable}` SET `{$core}` = LEFT(`{$core}`, {$limit}) WHERE CHAR_LENGTH(`{$core}`) > {$limit}"
        );

        $this->connection->executeStatement(
            "UPDATE `{$this->leadFieldsTable}` SET `char_length_limit` = ? WHERE `alias` = ? AND `object` = 'company'",
            [$limit, $core]
        );
    }

    private function rewriteSegmentFilters(): void
    {
        foreach ($this->fetchSegmentsWithLegacyFilter() as $id => $serializedFilters) {
            $filters = Serializer::decode($serializedFilters);
            if (!is_array($filters)) {
                continue;
            }

            foreach ($filters as &$filter) {
                if (is_array($filter) && self::LEGACY_ALIAS === ($filter['field'] ?? null)) {
                    $filter['field'] = self::CORE_ALIAS;
                }
            }
            unset($filter);

            $this->connection->executeStatement(
                "UPDATE `{$this->leadListsTable}` SET `filters` = ? WHERE `id` = ?",
                [serialize($filters), $id]
            );
        }
    }

    /**
     * @return array<int|string, string>
     */
    private function fetchSegmentsWithLegacyFilter(): array
    {
        $legacy = self::LEGACY_ALIAS;

        return $this->connection->fetchAllKeyValue(
            "SELECT `id`, `filters` FROM `{$this->leadListsTable}` WHERE `filters` LIKE ?",
            ['%s:5:"field";s:'.strlen($legacy).':"'.$legacy.'";%']
        );
    }

    private function hasLegacyColumn(): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->companiesTable, self::LEGACY_ALIAS]
        ) > 0;
    }

    private function hasLegacyField(): bool
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM `{$this->leadFieldsTable}` WHERE `alias` = ? AND `object` = 'company'",
            [self::LEGACY_ALIAS]
        ) > 0;
    }

    private function getCoreLimit(): ?int
    {
        $limit = $this->connection->fetchOne(
            "SELECT `char_length_limit` FROM `{$this->leadFieldsTable}` WHERE `alias` = ? AND `object` = 'company'",
            [self::CORE_ALIAS]
        );

        return false === $limit || null === $limit ? null : (int) $limit;
    }
}
