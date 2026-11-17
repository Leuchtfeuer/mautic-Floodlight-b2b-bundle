<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use Doctrine\DBAL\Connection;

class CompanyDescriptionFieldMigrator
{
    public const LIMIT = 191;

    private const CORE_ALIAS   = 'companydescription';
    private const LEGACY_ALIAS = 'f4b_companydescription';

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
        return $this->hasLegacyColumn()
            || $this->hasLegacyField()
            || self::LIMIT !== $this->getCoreLimit();
    }

    public function migrate(): void
    {
        $core   = self::CORE_ALIAS;
        $legacy = self::LEGACY_ALIAS;
        $limit  = self::LIMIT;

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
