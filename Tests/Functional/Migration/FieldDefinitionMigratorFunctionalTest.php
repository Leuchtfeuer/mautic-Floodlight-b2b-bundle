<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Migration;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FieldDefinitionMigrator;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\RebuildsCompanyTableAfterCustomFieldCleanup;

final class FieldDefinitionMigratorFunctionalTest extends MauticMysqlTestCase
{
    use RebuildsCompanyTableAfterCustomFieldCleanup;
    protected $useCleanupRollback = false;

    private function createMigrator(): FieldDefinitionMigrator
    {
        return new FieldDefinitionMigrator($this->getConnection());
    }

    private function getConnection(): Connection
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);

        return $connection;
    }

    /**
     * Creates a company custom field (and its physical column) the same way FieldModel
     * would on a real Mautic 7 install, i.e. VARCHAR columns are always capped at 191 chars.
     */
    private function createCompanyField(string $alias, string $type): LeadField
    {
        /** @var FieldModel $fieldModel */
        $fieldModel = self::getContainer()->get(FieldModel::class);

        $field = new LeadField();
        $field->setLabel($alias);
        $field->setAlias($alias);
        $field->setType($type);
        $field->setObject('company');
        $field->setGroup('core');
        $fieldModel->saveEntity($field);
        $this->em->flush();
        $this->em->clear();

        return $field;
    }

    public function testHasPendingMigrationIsFalseWhenNoF4bFieldsExist(): void
    {
        // None of the migrator's target fields exist in the base test schema until the
        // plugin is installed, so there is nothing for it to detect or migrate.
        $this->assertFalse($this->createMigrator()->hasPendingMigration());
    }

    public function testMigrateIsANoOpWhenNothingIsPending(): void
    {
        $stats = $this->createMigrator()->migrate();

        $this->assertSame(0, $stats['varchar_shrunk']);
        $this->assertSame(0, $stats['rows_truncated']);
        $this->assertFalse($stats['description_converted']);
        $this->assertSame(0, $stats['indexes_dropped']);
        $this->assertSame(0, $stats['limits_updated']);
    }

    public function testMigratorShrinksAndTruncatesVarcharFields(): void
    {
        $connection  = $this->getConnection();
        $table       = MAUTIC_TABLE_PREFIX.'companies';
        $fieldsTable = MAUTIC_TABLE_PREFIX.'lead_fields';

        $this->createCompanyField('f4b_companyname_legal', 'text');

        // Mautic 7 always caps new VARCHAR columns at 191 chars, so widen it by hand to
        // simulate a pre-M7 database that still has the old VARCHAR(255) column.
        $connection->executeStatement("ALTER TABLE `{$table}` MODIFY COLUMN `f4b_companyname_legal` VARCHAR(255) DEFAULT NULL");
        $connection->executeStatement("UPDATE `{$fieldsTable}` SET char_length_limit = 255 WHERE alias = 'f4b_companyname_legal' AND object = 'company'");

        $longValue = str_repeat('a', 220);
        $connection->executeStatement(
            "INSERT INTO `{$table}` (is_published, date_added, f4b_companyname_legal) VALUES (1, NOW(), ?)",
            [$longValue]
        );

        $migrator = $this->createMigrator();
        $this->assertTrue($migrator->hasPendingMigration());

        $stats = $migrator->migrate();

        $this->assertSame(1, $stats['varchar_shrunk']);
        $this->assertSame(1, $stats['rows_truncated']);
        $this->assertFalse($migrator->hasPendingMigration());

        $storedValue = $connection->fetchOne("SELECT f4b_companyname_legal FROM `{$table}` WHERE f4b_companyname_legal IS NOT NULL");
        $this->assertSame(191, strlen((string) $storedValue));

        $limit = $connection->fetchOne("SELECT char_length_limit FROM `{$fieldsTable}` WHERE alias = 'f4b_companyname_legal' AND object = 'company'");
        $this->assertSame(191, (int) $limit);
    }

    public function testMigratorConvertsDescriptionToTextarea(): void
    {
        $connection = $this->getConnection();
        $table      = MAUTIC_TABLE_PREFIX.'companies';

        // SchemaDefinition already creates a TEXT column for any alias containing
        // "description", so widen it back to VARCHAR by hand to simulate the pre-M7 state.
        $this->createCompanyField('f4b_companydescription', 'text');
        $connection->executeStatement("ALTER TABLE `{$table}` MODIFY COLUMN `f4b_companydescription` VARCHAR(255) DEFAULT NULL");

        $migrator = $this->createMigrator();
        $this->assertTrue($migrator->hasPendingMigration());

        $stats = $migrator->migrate();

        $this->assertTrue($stats['description_converted']);
        $this->assertFalse($migrator->hasPendingMigration());

        $dataType = $connection->fetchOne('
            SELECT DATA_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ', [$table, 'f4b_companydescription']);
        $this->assertSame('longtext', $dataType);

        $fieldType = $connection->fetchOne('SELECT `type` FROM '.MAUTIC_TABLE_PREFIX."lead_fields WHERE alias = 'f4b_companydescription' AND object = 'company'");
        $this->assertSame('textarea', $fieldType);
    }

    public function testMigratorDropsLegacyIndexes(): void
    {
        $connection  = $this->getConnection();
        $table       = MAUTIC_TABLE_PREFIX.'companies';
        $fieldsTable = MAUTIC_TABLE_PREFIX.'lead_fields';

        // Column is VARCHAR *and* indexed at the same time: on real MySQL, converting an
        // indexed VARCHAR column straight to LONGTEXT is rejected, so migrate() must drop
        // the index before converting the column.
        $this->createCompanyField('f4b_companydescription', 'text');
        $connection->executeStatement("ALTER TABLE `{$table}` MODIFY COLUMN `f4b_companydescription` VARCHAR(255) DEFAULT NULL");
        $connection->executeStatement("ALTER TABLE `{$table}` ADD INDEX `f4b_companydescription_search` (`f4b_companydescription`)");
        $connection->executeStatement("UPDATE `{$fieldsTable}` SET `is_index` = 1 WHERE alias = 'f4b_companydescription' AND object = 'company'");

        $migrator = $this->createMigrator();
        $this->assertTrue($migrator->hasPendingMigration());

        $stats = $migrator->migrate();

        $this->assertSame(1, $stats['indexes_dropped']);
        $this->assertTrue($stats['description_converted']);
        $this->assertFalse($migrator->hasPendingMigration());

        $indexCount = $connection->fetchOne('
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
        ', [$table, 'f4b_companydescription_search']);
        $this->assertSame(0, (int) $indexCount);

        $isIndex = $connection->fetchOne('SELECT is_index FROM '.MAUTIC_TABLE_PREFIX."lead_fields WHERE alias = 'f4b_companydescription' AND object = 'company'");
        $this->assertSame(0, (int) $isIndex);

        $dataType = $connection->fetchOne('
            SELECT DATA_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ', [$table, 'f4b_companydescription']);
        $this->assertSame('longtext', $dataType);
    }

    public function testMigratorFixesCharLengthLimit(): void
    {
        $connection  = $this->getConnection();
        $fieldsTable = MAUTIC_TABLE_PREFIX.'lead_fields';

        $this->createCompanyField('f4b_companytype', 'select');
        $connection->executeStatement("UPDATE `{$fieldsTable}` SET char_length_limit = NULL WHERE alias = 'f4b_companytype' AND object = 'company'");

        $migrator = $this->createMigrator();
        $this->assertTrue($migrator->hasPendingMigration());

        $stats = $migrator->migrate();

        $this->assertGreaterThanOrEqual(1, $stats['limits_updated']);
        $this->assertFalse($migrator->hasPendingMigration());

        $limit = $connection->fetchOne("SELECT char_length_limit FROM `{$fieldsTable}` WHERE alias = 'f4b_companytype' AND object = 'company'");
        $this->assertSame(191, (int) $limit);
    }
}
