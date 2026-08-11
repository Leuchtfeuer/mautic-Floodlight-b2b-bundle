<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Helper;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\LeadField;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDtoFactory;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Mapping\CompanyMapper;

class FieldHelperTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->client->request('GET', '/s/plugins/reload');

        $configPathTo   = self::getContainer()->get('mautic.helper.paths')->getSystemPath('root', true).'/config/f4b_domain_blacklist.php';
        $pluginPathFrom = self::getContainer()->get('mautic.helper.paths')->getSystemPath('plugins', true).'/'.LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME.'Bundle/Config/f4b_domain_blacklist.php';
        if (!file_exists($configPathTo)) {
            copy($pluginPathFrom, $configPathTo);
        }

        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testFieldPropertiesNotDoubleEncodedWhenAddingMultipleCompanies(): void
    {
        $dtoFactory    = self::getContainer()->get(FloodlightDtoFactory::class);
        $companyMapper = self::getContainer()->get(CompanyMapper::class);
        $fieldModel    = self::getContainer()->get('mautic.lead.model.field');
        $fieldHelper   = self::getContainer()->get(FieldHelper::class);
        $companyModel  = self::getContainer()->get('mautic.lead.model.company');

        // Step 1: Create first company with new field value containing ampersand
        $floodlightData1 = [
            'statusRequestCode'    => 200,
            'companyname'          => 'Test Company 1',
            'primarydomain'        => 'test1.com',
            'ip'                   => '1.2.3.4',
            'ip_hash'              => 'testhash1',
            'companyindustry_list' => ['Software & Computer Services', 'Internet'],
        ];

        $floodlightDto1 = $dtoFactory->create($floodlightData1);
        $company1       = new Company();
        $company1->setName('Test Company 1');
        $company1->setDateAdded(new \DateTime());
        $company1 = $companyMapper->mapToCompany($floodlightDto1, $company1);

        $fieldHelper->updateProperties($floodlightDto1);
        $companyModel->saveEntity($company1);
        $this->em->flush();
        $this->em->clear();

        // Step 2: Check field properties after first company
        $field = $fieldModel->getRepository()->findOneBy(['alias' => 'f4b_companyindustry_list']);
        $this->assertInstanceOf(LeadField::class, $field);

        $properties = $field->getProperties();
        $this->assertIsArray($properties);
        $this->assertArrayHasKey('list', $properties);

        $softwareProperty = null;
        foreach ($properties['list'] as $item) {
            if (isset($item['label']) && str_contains($item['label'], 'Software')) {
                $softwareProperty = $item;
                break;
            }
        }

        $this->assertNotNull($softwareProperty, 'Software & Computer Services property should exist');
        $this->assertStringContainsString('Software', $softwareProperty['label']);
        $this->assertStringContainsString('Computer Services', $softwareProperty['label']);

        // Count how many &#38; sequences exist - should be 1 (or 0 if properly decoded)
        $ampersandCount1 = substr_count($softwareProperty['label'], '&#38;');

        // Step 3: Create second company with DIFFERENT data to trigger property update
        $floodlightData2 = [
            'statusRequestCode'    => 200,
            'companyname'          => 'Test Company 2',
            'primarydomain'        => 'test2.com',
            'ip'                   => '5.6.7.8',
            'ip_hash'              => 'testhash2',
            'companyindustry_list' => ['Travel & Leisure', 'Pharmaceuticals & Biotechnology'],
        ];

        $floodlightDto2 = $dtoFactory->create($floodlightData2);
        $company2       = new Company();
        $company2->setName('Test Company 2');
        $company2->setDateAdded(new \DateTime());
        $company2 = $companyMapper->mapToCompany($floodlightDto2, $company2);

        $fieldHelper->updateProperties($floodlightDto2);
        $companyModel->saveEntity($company2);
        $this->em->flush();
        $this->em->clear();

        $field = $fieldModel->getRepository()->findOneBy(['alias' => 'f4b_companyindustry_list']);
        $this->assertInstanceOf(LeadField::class, $field);

        $properties = $field->getProperties();
        $this->assertIsArray($properties);
        $this->assertArrayHasKey('list', $properties);

        $softwarePropertyAfter = null;
        foreach ($properties['list'] as $item) {
            if (isset($item['label']) && str_contains($item['label'], 'Software')) {
                $softwarePropertyAfter = $item;
                break;
            }
        }

        $this->assertNotNull($softwarePropertyAfter, 'Software & Computer Services property should still exist');

        $ampersandCount2 = substr_count($softwarePropertyAfter['label'], '&#38;');

        $this->assertEquals(
            $ampersandCount1,
            $ampersandCount2,
            'Ampersand encoding count should not increase after adding second company. '.
            "Before: {$ampersandCount1} occurrences, After: {$ampersandCount2} occurrences. ".
            "Label: {$softwarePropertyAfter['label']}"
        );

        $this->assertStringNotContainsString(
            '&#38;#38;',
            $softwarePropertyAfter['label'],
            'Field property should not contain double-encoded ampersands (&#38;#38;)'
        );

        $travelProperty = null;
        foreach ($properties['list'] as $item) {
            if (isset($item['label']) && str_contains($item['label'], 'Travel')) {
                $travelProperty = $item;
                break;
            }
        }

        $this->assertNotNull($travelProperty, 'Travel & Leisure property should exist');
        $this->assertStringNotContainsString('&#38;#38;', $travelProperty['label'], 'New property should not have double-encoded ampersands');
    }

    public function testFieldPropertiesAreDecodedWhenLoaded(): void
    {
        $fieldModel  = self::getContainer()->get('mautic.lead.model.field');
        $fieldHelper = self::getContainer()->get(FieldHelper::class);

        $field = $fieldModel->getRepository()->findOneBy(['alias' => 'f4b_companyindustry_list']);
        $this->assertInstanceOf(LeadField::class, $field);

        $fieldHelper->addProperties($field, ['Energy & Power']);
        $this->em->flush();
        $this->em->clear();

        $field      = $fieldModel->getRepository()->findOneBy(['alias' => 'f4b_companyindustry_list']);
        $properties = $field->getProperties();

        $energyProperty = null;
        foreach ($properties['list'] as $item) {
            if (isset($item['label']) && str_contains($item['label'], 'Energy')) {
                $energyProperty = $item;
                break;
            }
        }

        $this->assertNotNull($energyProperty, 'Energy & Power property should exist');

        $fieldHelper->addProperties($field, ['Mining & Metals']);
        $this->em->flush();
        $this->em->clear();

        $field      = $fieldModel->getRepository()->findOneBy(['alias' => 'f4b_companyindustry_list']);
        $properties = $field->getProperties();

        $energyPropertyAfter = null;
        foreach ($properties['list'] as $item) {
            if (isset($item['label']) && str_contains($item['label'], 'Energy')) {
                $energyPropertyAfter = $item;
                break;
            }
        }

        $this->assertNotNull($energyPropertyAfter, 'Energy & Power property should still exist');
        $this->assertStringNotContainsString('&#38;#38;', $energyPropertyAfter['label'], 'Should not have double-encoded ampersand');
    }
}
