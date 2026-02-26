<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\DTO;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;

class FloodlightDTOTest extends MauticMysqlTestCase
{
    private FloodlightDTO $floodlightDTO;

    public function setUp(): void
    {
        parent::setUp();

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
        $domainUtility       = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.helper.utility.domain');
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
        $domainUtility       = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.helper.utility.domain');
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
        $floodlightCompanyDomainsModel = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.model.floodlightcompanydomains');
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
        $domainUtility       = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.helper.utility.domain');
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
        $domainUtility       = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.helper.utility.domain');
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
        $domainUtility       = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.helper.utility.domain');
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
        $domainUtility = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.helper.utility.domain');
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
            $field = new \Mautic\LeadBundle\Entity\LeadField();
            $field->setLabel($fieldData['name']);
            $field->setAlias($fieldData['alias']);
            $field->setType($fieldData['type']);
            $field->setObject($fieldData['object']);
            $field->setGroup('core');
            $field->setIsPublished(true);
            $field->setCharLengthLimit($fieldData['limit']);

            if (isset($fieldData['properties'])) {
                $field->setProperties($fieldData['properties']);
            }

            $fieldModel->saveEntity($field);
        }

        $this->em->flush();
        $this->em->clear();
    }
}
