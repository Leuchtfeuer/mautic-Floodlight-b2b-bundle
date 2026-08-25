<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests;

trait RebuildsCompanyTableAfterCustomFieldCleanup
{
    protected function beforeTearDown(): void
    {
        $tablePrefix = self::getContainer()->getParameter('mautic.db_table_prefix');
        $table       = $this->connection->quoteIdentifier($tablePrefix.'companies');

        // Purge columns retained by MariaDB's instant DROP COLUMN implementation.
        $this->connection->executeStatement(sprintf('ALTER TABLE %s ENGINE=InnoDB', $table));
    }
}
