<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\DTO;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;

class FloodlightDTOTest extends MauticMysqlTestCase
{
    private FloodlightDTO $floodlightDTO;

    public function setUp(): void
    {
        parent::setUp();

        $this->client->request('GET', '/s/plugins/reload');

        $configPathTo   = self::getContainer()->get('mautic.helper.paths')->getSystemPath('root', true).'/config/f4b_domain_blacklist.php';
        $pluginPathFrom = self::getContainer()->get('mautic.helper.paths')->getSystemPath('plugins', true).'/'.LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME.'Bundle/Config/f4b_domain_blacklist.php';
        if (!file_exists($configPathTo)) {
            copy($pluginPathFrom, $configPathTo);
        }

        $this->loadDTO();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testGettersAndSetters(): void
    {
        $name = $this->floodlightDTO->getCompanyName();
        $this->assertEquals('Brandenburgische Technische Universitt Cottbus-Senftenberg', $name);
        $this->assertEquals('Germany', $this->floodlightDTO->getCompanyCountry());
        $this->assertEquals('Cottbus', $this->floodlightDTO->getCompanyCity());
    }

    private function loadDTO(): void
    {
        $dataFake            = $this->fakeData();
        $fieldModel          = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility       = self::getContainer()->get(DomainUtility::class);
        $this->floodlightDTO = new FloodlightDTO($dataFake, $fieldModel, $domainUtility);
    }

    /**
     * @return array<string>
     */
    private function fakeData(int $type=1): array
    {
        $fileContent = file_get_contents(__DIR__.'/../fixtures/floodlight.json');
        if (2 === $type) {
            $fileContent = file_get_contents(__DIR__.'/../fixtures/floodlight2.json');
        }
        $this->assertIsString($fileContent);
        $data = json_decode($fileContent, true);
        $this->assertIsArray($data);

        return $data;
    }

    public function testGetCompanyEntity(): void
    {
        $fieldModel          = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility       = self::getContainer()->get(DomainUtility::class);
        $floodlightDto       = new FloodlightDTO($this->fakeData(), $fieldModel, $domainUtility);
        $company             = new Company();
        $newCompany          = $floodlightDto->fillCompany($company);
        $this->assertInstanceOf(Company::class, $newCompany);
        $this->assertEquals('Brandenburgische Technische Universitt Cottbus-Senftenberg', $newCompany->getName());
        $this->assertEquals('Germany', $newCompany->getCountry());
        $this->assertEquals('Cottbus', $newCompany->getCity());
        $this->assertEquals('Platz Der Deutschen Einheit 1', $newCompany->getAddress1());
        $this->assertEquals(3001, $this->floodlightDTO->getCompanyNumberOfEmployees());
        $this->assertNotEmpty($this->floodlightDTO->getCompanyNumberOfEmployees());
    }

    public function testFillCompaniesDomain(): void
    {
        $floodlightCompanyDomainsModel = self::getContainer()->get(FloodlightCompanyDomainsModel::class);
        $company                       = new Company();
        $domains                       = $this->floodlightDTO->getDomains();
        $domains[]                     = 'www.example.com';
        $domains[]                     = 'www.leuchtfeuer.com';
        $this->floodlightDTO->setDomains($domains);
        $result = $this->floodlightDTO->fillCompaniesDomain($floodlightCompanyDomainsModel, $company);
        $this->assertNotEmpty($result);
        $this->assertCount(3, $result);
        $this->assertTrue($result[0]->getIsPrimaryDomain());
        $this->assertFalse($result[1]->getIsPrimaryDomain());
        $this->assertFalse($result[2]->getIsPrimaryDomain());
        $this->assertSame('example.com', $result[1]->getDomain());
        $this->assertSame('leuchtfeuer.com', $result[2]->getDomain());
    }

    public function testFillCompanyDomain(): void
    {
        $fieldModel          = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility       = self::getContainer()->get(DomainUtility::class);
        $floodlightDto       = new FloodlightDTO($this->fakeData(2), $fieldModel, $domainUtility);
        $company             = new Company();
        $company->setName('Casa da Moeda do Brasil');
        $company->setCountry('Brazil');
        $company->setCity('Brasilia');
        $company->setAddress1('Address 1');
        $company->setDateAdded(new \DateTime());

        $this->em->persist($company);
        $this->em->flush();

        $newCompany = $floodlightDto->fillCompany($company);

        $this->assertInstanceOf(Company::class, $newCompany);
        $this->assertEquals('Casa da Moeda do Brasil', $newCompany->getName());
        $this->assertEquals('Germany', $newCompany->getCountry());
        $this->assertEquals('Cottbus', $newCompany->getCity());
        $this->assertEquals('Platz Der Deutschen Einheit 1', $newCompany->getAddress1());
        $this->assertEquals(3001, $this->floodlightDTO->getCompanyNumberOfEmployees());
    }

    public function testFillLeadDomain(): void
    {
        $fieldModel          = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility       = self::getContainer()->get(DomainUtility::class);
        $floodlightDto       = new FloodlightDTO($this->fakeData(), $fieldModel, $domainUtility);
        $leadDomain          = $floodlightDto->fillLeadDomainLookUp();
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomain);
        $this->assertNotSame('local', $leadDomain->getIpHash());
        $this->assertSame(1, $leadDomain->getCount());
    }

    public function testFillLeadDomainNoIpHash(): void
    {
        $data                =$this->fakeData();
        $fieldModel          = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility       = self::getContainer()->get(DomainUtility::class);
        unset($data['ip_hash']);
        $floodlightDto = new FloodlightDTO($data, $fieldModel, $domainUtility);
        $leadDomain    = $floodlightDto->fillLeadDomainLookUp();
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomain);
        $this->assertSame('local', $leadDomain->getIpHash());
        $this->assertSame('le-non.de', $leadDomain->getDomain());
    }

    public function testFloodlightApiValuesGetTruncatedWhenExceedingFieldLimits(): void
    {
        $this->createTestFields();

        $this->em->flush();
        $this->em->clear();

        $fieldModel    = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility = self::getContainer()->get(DomainUtility::class);
        $floodlightDto = new FloodlightDTO($this->fakeData(), $fieldModel, $domainUtility);
        $company       = new Company();
        $newCompany    = $floodlightDto->fillCompany($company);

        $this->assertInstanceOf(Company::class, $newCompany);

        $technologies = $company->getFieldValue('f4b_companytechnologies');
        $this->assertIsString($technologies, 'Technologies should be a string');
        $this->assertLessThanOrEqual(255, strlen($technologies));

        $longDescription = $company->getFieldValue('f4b_companydescription_long');
        $this->assertIsString($longDescription, 'Long description should be a string');
        $this->assertLessThanOrEqual(2560, strlen($longDescription));
    }

    private function createTestFields(): void
    {
        $fieldModel = self::getContainer()->get('mautic.lead.model.field');

        $testFields = [
            [
                'alias'  => 'f4b_companydescription_long',
                'name'   => 'Company Long Description',
                'type'   => 'textarea',
                'limit'  => 2560,
                'object' => 'company',
            ],
            [
                'alias'      => 'f4b_companytechnologies',
                'name'       => 'Technologies',
                'limit'      => 255,
                'type'       => 'multiselect',
                'object'     => 'company',
                'properties' => [
                    'list' => [
                        ['label' => 'Mautic', 'value' => 'mautic'],
                    ],
                ],
            ],
        ];

        foreach ($testFields as $fieldData) {
            $field = $fieldModel->getRepository()->findOneBy(['alias' => $fieldData['alias']]);

            if (!$field) {
                $field = new \Mautic\LeadBundle\Entity\LeadField();
                $field->setLabel($fieldData['name']);
                $field->setAlias($fieldData['alias']);
                $field->setType($fieldData['type']);
                $field->setObject($fieldData['object']);
                $field->setGroup('core');
                $field->setIsPublished(true);
            }

            $field->setCharLengthLimit($fieldData['limit']);

            if (isset($fieldData['properties'])) {
                $field->setProperties($fieldData['properties']);
            }

            $fieldModel->saveEntity($field);
        }

        $this->em->flush();
        $this->em->clear();
    }

    public function testListFieldValuesAreNormalizedWithDashes(): void
    {
        $fieldModel    = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility = self::getContainer()->get(DomainUtility::class);

        // Update existing field or create new one
        $field = $fieldModel->getRepository()->findOneBy(['alias' => 'f4b_companyindustry_icb_industry']);
        if (!$field) {
            $field = new \Mautic\LeadBundle\Entity\LeadField();
            $field->setName('Industry ICB');
            $field->setAlias('f4b_companyindustry_icb_industry');
            $field->setType('select');
            $field->setObject('company');
            $field->setIsPublished(true);
        }
        $field->setProperties([
            'list' => [
                ['label' => 'Software & Computer Services', 'value' => 'Software-&-Computer-Services'],
                ['label' => 'Travel & Tourism', 'value' => 'Travel-&-Tourism'],
                ['label' => 'Banks', 'value' => 'Banks'],
            ],
        ]);
        $fieldModel->saveEntity($field);
        $this->em->flush();

        // Simulate Floodlight API data with spaces (legacy format)
        $floodlightData = [
            'companyindustry_icb_industry' => 'Software & Computer Services',
            'statusRequestCode'            => 200,
        ];

        $dto = new FloodlightDTO($floodlightData, $fieldModel, $domainUtility);

        $this->assertSame('Software-&-Computer-Services', $dto->getCompanyIndustryICBCIndustry());

        $company = new Company();
        $dto->fillCompany($company);

        $this->assertSame('Software-&-Computer-Services', $company->getFieldValue('f4b_companyindustry_icb_industry'));
    }

    public function testListFieldMultiselectValuesAreNormalized(): void
    {
        $fieldModel    = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility = self::getContainer()->get(DomainUtility::class);

        $field = $fieldModel->getRepository()->findOneBy(['alias' => 'f4b_companyindustry_list']);
        if (!$field) {
            $field = new \Mautic\LeadBundle\Entity\LeadField();
            $field->setName('Industry List');
            $field->setAlias('f4b_companyindustry_list');
            $field->setType('multiselect');
            $field->setObject('company');
            $field->setIsPublished(true);
        }
        $field->setProperties([
            'list' => [
                ['label' => 'Software & Computer Services', 'value' => 'Software-&-Computer-Services'],
                ['label' => 'Computer Services', 'value' => 'Computer-Services'],
            ],
        ]);
        $fieldModel->saveEntity($field);
        $this->em->flush();

        // Simulate Floodlight API data with spaces (legacy format)
        $floodlightData = [
            'companyindustry_list' => ['Software & Computer Services', 'Computer Services'],
            'statusRequestCode'    => 200,
        ];

        $dto = new FloodlightDTO($floodlightData, $fieldModel, $domainUtility);

        $normalized = $dto->getCompanyIndustryList();
        $this->assertSame(['Software-&-Computer-Services', 'Computer-Services'], $normalized);
    }

    public function testNonListFieldValuesRemainUnchanged(): void
    {
        $fieldModel    = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility = self::getContainer()->get(DomainUtility::class);

        // Simulate data with spaces in a non-list field
        $floodlightData = [
            'companydescription' => 'Software & Consulting Services',
            'statusRequestCode'  => 200,
        ];

        $dto = new FloodlightDTO($floodlightData, $fieldModel, $domainUtility);

        // Non-list field should keep spaces (not normalized)
        $this->assertSame('Software & Consulting Services', $dto->getCompanyDescription());
    }

    public function testNoDoubleEncodingOnMultipleUpdates(): void
    {
        $fieldsToTest = [
            'f4b_companyindustry_list',
        ];

        $floodlightData = [
            'statusRequestCode'    => 200,
            'companyname'          => 'Test Company',
            'primarydomain'        => 'test.com',
            'ip'                   => '1.2.3.4',
            'ip_hash'              => 'testhash',
            'companyindustry_list' => ['Software & Computer Services', 'Travel & Leisure'],
        ];

        $fieldModel     = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility  = self::getContainer()->get(DomainUtility::class);
        $fieldHelper    = self::getContainer()->get(FieldHelper::class);
        $companyModel   = self::getContainer()->get('mautic.lead.model.company');

        // Step 1: Create first company
        $floodlightDto = new FloodlightDTO($floodlightData, $fieldModel, $domainUtility);
        $company       = new Company();
        $company->setName('Test Company 1');
        $company->setDateAdded(new \DateTime());

        $company = $floodlightDto->fillCompany($company);
        $fieldHelper->updateProperties($floodlightDto);
        $companyModel->saveEntity($company);
        $this->em->flush();
        $this->em->clear();

        // Get the values after first save
        $savedCompany         = $companyModel->getEntity($company->getId());
        $valuesAfterFirstSave = [];
        foreach ($fieldsToTest as $fieldToTest) {
            $value = $savedCompany->getFieldValue($fieldToTest);
            $this->assertNotEmpty($value, "Field {$fieldToTest} should not be empty after first save");
            $this->assertStringNotContainsString('&#38;#38;', $value, "Field {$fieldToTest} should not contain double-encoded ampersands after first save");
            $valuesAfterFirstSave[$fieldToTest] = $value;
        }

        // Step 2: Create second company with same data
        $floodlightDto2 = new FloodlightDTO($floodlightData, $fieldModel, $domainUtility);
        $company2       = new Company();
        $company2->setName('Test Company 2');
        $company2->setDateAdded(new \DateTime());

        $company2 = $floodlightDto2->fillCompany($company2);
        $fieldHelper->updateProperties($floodlightDto2);
        $companyModel->saveEntity($company2);
        $this->em->flush();
        $this->em->clear();

        // Values should be identical - no double encoding
        $savedCompany2 = $companyModel->getEntity($company2->getId());
        foreach ($fieldsToTest as $fieldToTest) {
            $value = $savedCompany2->getFieldValue($fieldToTest);
            $this->assertEquals($valuesAfterFirstSave[$fieldToTest], $value, "Field {$fieldToTest}: Values should be identical - no double encoding should occur");
            $this->assertStringNotContainsString('&#38;#38;', $value, "Field {$fieldToTest} should not contain double-encoded ampersands after second save");
        }

        // Step 3: Update the first company again
        $savedCompany   = $companyModel->getEntity($company->getId());
        $floodlightDto3 = new FloodlightDTO($floodlightData, $fieldModel, $domainUtility);
        $savedCompany   = $floodlightDto3->fillCompany($savedCompany);
        $fieldHelper->updateProperties($floodlightDto3);
        $companyModel->saveEntity($savedCompany);
        $this->em->flush();
        $this->em->clear();

        // Value should remain identical after multiple updates
        $savedCompany = $companyModel->getEntity($company->getId());
        foreach ($fieldsToTest as $fieldToTest) {
            $value = $savedCompany->getFieldValue($fieldToTest);
            $this->assertEquals($valuesAfterFirstSave[$fieldToTest], $value, "Field {$fieldToTest}: Value should remain identical after multiple updates");
            $this->assertStringNotContainsString('&#38;#38;', $value, "Field {$fieldToTest} should not contain double-encoded ampersands after third save");
        }
    }

    public function testArrayValuesAreSanitizedAndNormalized(): void
    {
        $fieldModel    = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility = self::getContainer()->get(DomainUtility::class);

        // Ensure the field has a list property for the test
        $field = $fieldModel->getRepository()->findOneBy(['alias' => 'f4b_companyindustry_list']);
        if ($field) {
            $field->setProperties([
                'list' => [
                    ['label' => 'Software & Computer Services', 'value' => 'Software-&-Computer-Services'],
                    ['label' => 'Travel & Leisure', 'value' => 'Travel-&-Leisure'],
                ],
            ]);
            $fieldModel->saveEntity($field);
            $this->em->flush();
        }

        $floodlightData = [
            'statusRequestCode'    => 200,
            'companyname'          => 'Test Company',
            'primarydomain'        => 'test.com',
            'ip'                   => '1.2.3.4',
            'ip_hash'              => 'testhash',
            'companyindustry_list' => ['Software &#38; Computer Services', 'Travel &#38; Leisure'],
        ];

        $floodlightDto = new FloodlightDTO($floodlightData, $fieldModel, $domainUtility);
        $company       = new Company();
        $company->setName('Test Company');
        $company->setDateAdded(new \DateTime());

        $company = $floodlightDto->fillCompany($company);

        // Check that the array values were sanitized AND normalized (spaces → dashes for list fields)
        $industryListValue = $company->getFieldValue('f4b_companyindustry_list');
        $this->assertIsString($industryListValue);
        $this->assertStringContainsString('Software-&-Computer-Services', $industryListValue, 'Should be normalized: spaces → dashes');
        $this->assertStringContainsString('Travel-&-Leisure', $industryListValue, 'Should be normalized: spaces → dashes');
        $this->assertStringNotContainsString('&#38;', $industryListValue, 'HTML entities should be decoded');
        $this->assertStringNotContainsString(' & ', $industryListValue, 'Spaces should be replaced with dashes for list fields');
    }
}
