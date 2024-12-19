<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\DTO;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;

class FloodlightDTOTest extends MauticMysqlTestCase
{
    private FloodlightDTO $floodlightDTO;

    public function setUp(): void
    {
        parent::setUp();
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
        $this->floodlightDTO = new FloodlightDTO($dataFake);
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
        $floodlightDto = new FloodlightDTO($this->fakeData());
        $company       = new Company();
        $newCompany    = $floodlightDto->fillCompany($company);
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
        $this->assertSame('www.example.com', $result[1]->getDomain());
        $this->assertSame('www.leuchtfeuer.com', $result[2]->getDomain());
    }

    public function testFillCompanyDomain(): void
    {
        $floodlightDto = new FloodlightDTO($this->fakeData(2));
        $company       = new Company();
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
        $floodlightDto = new FloodlightDTO($this->fakeData());
        $leadDomain    = $floodlightDto->fillLeadDomainLookUp();
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomain);
        $this->assertNotSame('local', $leadDomain->getIpHash());
        $this->assertSame(1, $leadDomain->getCount());
    }

    public function testFillLeadDomainNoIpHash(): void
    {
        $data =$this->fakeData();
        unset($data['ip_hash']);
        $floodlightDto = new FloodlightDTO($data);
        $leadDomain    = $floodlightDto->fillLeadDomainLookUp();
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomain);
        $this->assertSame('local', $leadDomain->getIpHash());
        $this->assertSame('le-non.de', $leadDomain->getDomain());
    }
}
