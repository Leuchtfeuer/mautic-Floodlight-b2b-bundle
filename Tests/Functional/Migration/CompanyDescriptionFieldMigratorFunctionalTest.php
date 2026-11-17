<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Migration;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\CompanyDescriptionFieldMigrator;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\RebuildsCompanyTableAfterCustomFieldCleanup;

final class CompanyDescriptionFieldMigratorFunctionalTest extends MauticMysqlTestCase
{
    use RebuildsCompanyTableAfterCustomFieldCleanup;
    protected $useCleanupRollback = false;

    public function testMigrateDropsLegacyFieldAndLimitsCoreField(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        $table      = MAUTIC_TABLE_PREFIX.'companies';
        $fields     = MAUTIC_TABLE_PREFIX.'lead_fields';

        $field = new LeadField();
        $field->setLabel('Company Description');
        $field->setAlias('f4b_companydescription');
        $field->setType('textarea');
        $field->setObject('company');
        $field->setGroup('core');
        self::getContainer()->get(FieldModel::class)->saveEntity($field);

        $legacySegment = $this->createSegment('legacy', 'f4b_companydescription');
        $longSegment   = $this->createSegment('long', 'f4b_companydescription_long');
        $this->em->clear();

        $longDescription = str_repeat('ä', 250);
        $connection->executeStatement(
            "INSERT INTO `{$table}` (`is_published`, `companyname`, `companydescription`) VALUES
                (1, 'short', 'Own text'),
                (1, 'too-long', ?)",
            [$longDescription]
        );

        $migrator = new CompanyDescriptionFieldMigrator($connection);
        $this->assertTrue($migrator->hasPendingMigration());

        $migrator->migrate();

        $this->assertFalse($migrator->hasPendingMigration());
        $this->assertFalse($connection->createSchemaManager()->introspectTable($table)->hasColumn('f4b_companydescription'));
        $this->assertSame(0, (int) $connection->fetchOne("SELECT COUNT(*) FROM `{$fields}` WHERE alias = 'f4b_companydescription'"));
        $this->assertSame(
            CompanyDescriptionFieldMigrator::LIMIT,
            (int) $connection->fetchOne("SELECT char_length_limit FROM `{$fields}` WHERE alias = 'companydescription' AND object = 'company'")
        );

        $descriptions = $connection->fetchAllKeyValue("SELECT companyname, companydescription FROM `{$table}`");
        $this->assertSame('Own text', $descriptions['short']);
        $this->assertSame(mb_substr($longDescription, 0, CompanyDescriptionFieldMigrator::LIMIT), $descriptions['too-long']);

        $this->assertSame('companydescription', $this->em->find(LeadList::class, $legacySegment)?->getFilters()[0]['field']);
        $this->assertSame('f4b_companydescription_long', $this->em->find(LeadList::class, $longSegment)?->getFilters()[0]['field']);
    }

    private function createSegment(string $alias, string $field): int
    {
        $segment = new LeadList();
        $segment->setName($alias);
        $segment->setPublicName($alias);
        $segment->setAlias($alias);
        $segment->setFilters([[
            'glue'       => 'and',
            'field'      => $field,
            'object'     => 'company',
            'type'       => 'textarea',
            'operator'   => '!empty',
            'properties' => ['filter' => null],
        ]]);
        $this->em->persist($segment);
        $this->em->flush();

        return (int) $segment->getId();
    }
}
