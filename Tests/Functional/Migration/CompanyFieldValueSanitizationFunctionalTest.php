<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Migration;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\CompanyFieldValueSanitizer;
use PHPUnit\Framework\Assert;

final class CompanyFieldValueSanitizationFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private function createSanitizer(): CompanyFieldValueSanitizer
    {
        /** @var Connection $connection */
        $connection = self::$container->get('doctrine.dbal.default_connection');

        return new CompanyFieldValueSanitizer($connection);
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function createOrUpdateField(string $alias, string $name, string $type, array $properties): LeadField
    {
        /** @var FieldModel $fieldModel */
        $fieldModel = self::$container->get('mautic.lead.model.field');

        // Check if field exists
        $existingField = $this->em->getRepository(LeadField::class)->findOneBy(['alias' => $alias]);

        if ($existingField) {
            // Update existing field with test data
            $existingField->setProperties($properties);
            $fieldModel->saveEntity($existingField);
            $this->em->flush();

            return $existingField;
        }

        // Create new field
        $field = new LeadField();
        $field->setName($name);
        $field->setAlias($alias);
        $field->setType($type);
        $field->setObject('company');
        $field->setProperties($properties);
        $fieldModel->saveEntity($field);
        $this->em->flush();

        return $field;
    }

    public function testSanitizerDetectsAndFixesMultipleEncodedValues(): void
    {
        // Use real F4B field alias from FIELDS_TO_CHECK
        $testAlias = 'f4b_companyindustry_icb_industry';

        // Realistic corrupted data from screenshot
        $corruptedList = [
            // Triple encoded ampersand (like in screenshot)
            ['label' => 'Software &#38;#38;#38; Computer Services', 'value' => 'Software-&#38;#38;#38;-Computer-Services'],
            ['label' => 'Software &#38;#38;#38; Computer Services', 'value' => 'Software-&#38;#38;#38;-Computer-Services'], // exact duplicate
            // Single encoded
            ['label' => 'Travel &#38; Tourism', 'value' => 'Travel-&#38;-Tourism'],
            ['label' => 'Travel &#38; Tourism', 'value' => 'Travel-&#38;-Tourism'], // duplicate
            // Clean value
            ['label' => 'Government', 'value' => 'Government'],
        ];

        $this->createOrUpdateField($testAlias, 'Test Industry', 'select', ['list' => $corruptedList]);

        $this->em->clear();

        $sanitizer = $this->createSanitizer();
        Assert::assertTrue($sanitizer->hasPendingSanitization());

        $stats = $sanitizer->sanitize();

        Assert::assertGreaterThan(0, $stats['fields_updated'], 'At least 1 field should be updated');
        Assert::assertSame(2, $stats['duplicates_removed'], '2 duplicates should be removed');
        Assert::assertFalse($sanitizer->hasPendingSanitization());

        $this->em->clear();
        $reloadedField = $this->em->getRepository(LeadField::class)->findOneBy(['alias' => $testAlias]);
        Assert::assertInstanceOf(LeadField::class, $reloadedField);

        $properties = $reloadedField->getProperties();
        $actualList = $properties['list'];

        Assert::assertCount(3, $actualList, 'Should have 3 unique values after deduplication');

        foreach ($actualList as $item) {
            Assert::assertStringNotContainsString('&#38;', $item['label'], 'Labels should be clean');
            Assert::assertStringNotContainsString('&#38;', $item['value'], 'Values should be clean');
        }

        $actualValues = array_column($actualList, 'value');

        Assert::assertContains('Software-&-Computer-Services', $actualValues);
        Assert::assertContains('Travel-&-Tourism', $actualValues);
        Assert::assertContains('Government', $actualValues);
    }

    public function testSanitizerHandlesMultiselectValues(): void
    {
        $testAlias = 'f4b_companylabels';

        $corruptedList = [
            ['label' => 'Tech &#38;#38; Innovation', 'value' => 'Tech-&#38;#38;-Innovation'],
            ['label' => 'Tech &#38;#38; Innovation', 'value' => 'Tech-&#38;#38;-Innovation'], // duplicate
            ['label' => 'B2B &#38; Enterprise', 'value' => 'B2B-&#38;-Enterprise'],
        ];

        $this->createOrUpdateField($testAlias, 'Test Labels', 'multiselect', ['list' => $corruptedList]);

        $this->em->clear();

        $sanitizer = $this->createSanitizer();
        Assert::assertTrue($sanitizer->hasPendingSanitization());

        $stats = $sanitizer->sanitize();

        Assert::assertSame(1, $stats['duplicates_removed'], '1 duplicate should be removed');

        $this->em->clear();
        $reloadedField = $this->em->getRepository(LeadField::class)->findOneBy(['alias' => $testAlias]);
        Assert::assertInstanceOf(LeadField::class, $reloadedField);

        $properties = $reloadedField->getProperties();
        $list       = $properties['list'];

        Assert::assertCount(2, $list, 'Should have 2 unique values');

        foreach ($list as $item) {
            Assert::assertStringNotContainsString('&#38;', $item['label']);
            Assert::assertStringNotContainsString('&#38;', $item['value']);
            Assert::assertStringContainsString('&', $item['label'], 'Should contain real ampersand');
        }
    }

    public function testSanitizerSkipsFieldsWithoutCorruption(): void
    {
        $testAlias = 'f4b_companytype';

        $cleanList = [
            ['label' => 'Public Company', 'value' => 'Public-Company'],
            ['label' => 'Private Company', 'value' => 'Private-Company'],
            ['label' => 'Non-Profit', 'value' => 'Non-Profit'],
        ];

        $this->createOrUpdateField($testAlias, 'Test Clean', 'select', ['list' => $cleanList]);

        $this->em->clear();

        $sanitizer = $this->createSanitizer();

        Assert::assertFalse($sanitizer->hasPendingSanitization());

        $stats = $sanitizer->sanitize();

        Assert::assertSame(0, $stats['fields_updated']);
        Assert::assertSame(0, $stats['companies_updated']);
        Assert::assertSame(0, $stats['duplicates_removed']);
    }

    public function testSanitizerIgnoresFieldsNotInF4BList(): void
    {
        // Field NOT in FIELDS_TO_CHECK - should be completely ignored
        $nonF4BAlias = 'some_other_company_field';

        $corruptedList = [
            ['label' => 'Value &#38;#38; One', 'value' => 'Value-&#38;#38;-One'],
            ['label' => 'Value &#38;#38; Two', 'value' => 'Value-&#38;#38;-Two'],
        ];

        $this->createOrUpdateField($nonF4BAlias, 'Non-F4B Field', 'select', ['list' => $corruptedList]);

        $this->em->clear();

        $sanitizer = $this->createSanitizer();

        // Should NOT detect corruption because field is not in F4B list
        Assert::assertFalse($sanitizer->hasPendingSanitization(), 'Non-F4B fields should be ignored');

        $stats = $sanitizer->sanitize();

        Assert::assertSame(0, $stats['fields_updated'], 'Non-F4B field should not be updated');
        Assert::assertSame(0, $stats['duplicates_removed']);

        // Verify field remains unchanged
        $this->em->clear();
        $reloadedField = $this->em->getRepository(LeadField::class)->findOneBy(['alias' => $nonF4BAlias]);
        Assert::assertInstanceOf(LeadField::class, $reloadedField);

        $properties = $reloadedField->getProperties();
        $list       = $properties['list'];

        // Should still have HTML entities (not sanitized)
        Assert::assertStringContainsString('&#38;', $list[0]['label'], 'Non-F4B field should remain unchanged');
    }

    public function testSanitizerNormalizesCompanyValuesWithSpaces(): void
    {
        $testAlias = 'f4b_companyindustry_icb_industry';

        // Clean field list with dashes (expected format)
        $cleanList = [
            ['label' => 'Software & Computer Services', 'value' => 'Software-&-Computer-Services'],
            ['label' => 'Travel & Tourism', 'value' => 'Travel-&-Tourism'],
            ['label' => 'Banks', 'value' => 'Banks'],
        ];

        $this->createOrUpdateField($testAlias, 'Test Industry', 'select', ['list' => $cleanList]);

        /** @var Connection $connection */
        $connection = self::$container->get('doctrine.dbal.default_connection');

        // Create test companies with legacy Floodlight values (spaces instead of dashes)
        $connection->executeStatement('
            INSERT INTO '.MAUTIC_TABLE_PREFIX."companies (is_published, date_added, {$testAlias})
            VALUES
                (1, NOW(), 'Software & Computer Services'),
                (1, NOW(), 'Travel & Tourism'),
                (1, NOW(), 'Banks')
        ");

        $this->em->clear();

        $sanitizer = $this->createSanitizer();
        Assert::assertFalse($sanitizer->hasPendingSanitization(), 'Field list is already clean');

        $stats = $sanitizer->sanitize();

        Assert::assertSame(0, $stats['fields_updated'], 'Field list should not be updated');
        Assert::assertSame(2, $stats['companies_updated'], '2 companies should be updated (with spaces)');

        $companies = $connection->fetchAllAssociative("
            SELECT {$testAlias} FROM ".MAUTIC_TABLE_PREFIX."companies
            WHERE {$testAlias} IS NOT NULL
            ORDER BY {$testAlias}
        ");

        Assert::assertCount(3, $companies);

        // All values should now have dashes, not spaces
        Assert::assertSame('Banks', $companies[0][$testAlias], 'No spaces, should remain unchanged');
        Assert::assertSame('Software-&-Computer-Services', $companies[1][$testAlias], 'Spaces should be replaced with dashes');
        Assert::assertSame('Travel-&-Tourism', $companies[2][$testAlias], 'Spaces should be replaced with dashes');
    }

    public function testSanitizerNormalizesMultiselectCompanyValues(): void
    {
        $testAlias = 'f4b_companyindustry_list';

        // Clean field list
        $cleanList = [
            ['label' => 'Software & Computer Services', 'value' => 'Software-&-Computer-Services'],
            ['label' => 'Computer Services', 'value' => 'Computer-Services'],
            ['label' => 'IT Services', 'value' => 'IT-Services'],
        ];

        $this->createOrUpdateField($testAlias, 'Test Industry List', 'multiselect', ['list' => $cleanList]);

        /** @var Connection $connection */
        $connection = self::$container->get('doctrine.dbal.default_connection');

        // Create company with pipe-separated values containing spaces
        $connection->executeStatement('
            INSERT INTO '.MAUTIC_TABLE_PREFIX."companies (is_published, date_added, {$testAlias})
            VALUES (1, NOW(), 'Software & Computer Services|Computer Services')
        ");

        $this->em->clear();

        // Run sanitizer
        $sanitizer = $this->createSanitizer();
        $stats     = $sanitizer->sanitize();

        Assert::assertSame(1, $stats['companies_updated'], '1 company with multiselect should be updated');

        // Verify multiselect values are normalized
        $company = $connection->fetchAssociative("
            SELECT {$testAlias} FROM ".MAUTIC_TABLE_PREFIX."companies
            WHERE {$testAlias} IS NOT NULL
        ");

        Assert::assertSame('Software-&-Computer-Services|Computer-Services', $company[$testAlias]);
    }
}
