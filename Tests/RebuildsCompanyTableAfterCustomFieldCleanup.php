<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests;

trait RebuildsCompanyTableAfterCustomFieldCleanup
{
    protected function afterRollback(): void
    {
        $connection  = $this->em->getConnection();
        $tablePrefix = self::getContainer()->getParameter('mautic.db_table_prefix');
        $table       = $connection->quoteIdentifier($tablePrefix.'companies');

        // Purge columns retained by MariaDB's instant DROP COLUMN implementation.
        // Otherwise the row size limit of the companies table is reached after a few tests that create custom fields.
        $connection->executeStatement(sprintf('ALTER TABLE %s ENGINE=InnoDB', $table));
    }
}
