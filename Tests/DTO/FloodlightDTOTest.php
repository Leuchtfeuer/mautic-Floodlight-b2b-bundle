<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\DTO;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;

class FloodlightDTOTest extends MauticMysqlTestCase
{
    private FloodlightDTO $floodlightDTO;

    public function setUp(): void
    {
        parent::setUp();
        $this->loadDTO();
    }

    public function testGettersAndSetters(): void
    {
        $name = $this->floodlightDTO->getName();
        $this->assertEquals('Goldman Sachs Group', $name);
        $this->assertEquals('Germany', $this->floodlightDTO->getCountry());
        $this->assertEquals('Hannover', $this->floodlightDTO->getCity());
        $this->assertEquals('Main street 1 Mansion House', $this->floodlightDTO->getAddressLine1());
        $this->assertEquals('https://www.goldmansachs.com/', $this->floodlightDTO->getWebsite());
        $this->assertEquals('0', $this->floodlightDTO->getNumberOfEmployees());

        $this->floodlightDTO->setName('Leuchtfeuer Digital Marketing');
        $this->assertEquals('Leuchtfeuer Digital Marketing', $this->floodlightDTO->getName());
        $this->floodlightDTO->setCountry('Portugal');
        $this->assertEquals('Portugal', $this->floodlightDTO->getCountry());
        $this->floodlightDTO->setCity('Lisbon');
        $this->assertEquals('Lisbon', $this->floodlightDTO->getCity());
        $this->floodlightDTO->setAddressLine1('Rua da Prata 80');
        $this->assertEquals('Rua da Prata 80', $this->floodlightDTO->getAddressLine1());
        $this->floodlightDTO->setWebsite('https://leuchtfeuer.com/digital-marketing');
        $this->assertEquals('https://leuchtfeuer.com/digital-marketing', $this->floodlightDTO->getWebsite());
        $this->floodlightDTO->setNumberOfEmployees(50);
        $this->assertEquals(50, $this->floodlightDTO->getNumberOfEmployees());
    }

    private function loadDTO(): void
    {
        $dataFake            = json_decode($this->fakeData(), true);
        $data['content']     = $dataFake;
        $this->floodlightDTO = new FloodlightDTO($data);
    }

    private function fakeData(): string
    {
        return '{
                    "domain": "http://abc.de ",
                    "company": {
                        "id": "60b725f10c9c85c70d97880dfe8191b3",
                        "primary_domain": "http://goldmansachs.com ",
                        "name": "Goldman Sachs Group",
                        "address1": "Main street 1",
                        "address2": "Mansion House",
                        "address2": "Mansion House",
                        "city": "Hannover",
                        "zip": "30177",
                        "country": "Germany",
                        "legalName": "The Goldman Sachs Group, Inc.",
                        "logo": "http://<hostname>/logo/goldmansachs.com",
                        "url": "https://www.goldmansachs.com/",
                        "wzl": "23457",
                        "naics": "abc",
                        "isic": "123",
                        "nace": "xyz",
                        "uk-sic": "987654",
                        "industry tags": [
                            "Finance",
                            "Financial Services",
                            "FinTech"
                        ]
                    }
                }';
    }

    public function testGetCompanyEntity(): void
    {
        $company    = new Company();
        $newCompany = $this->floodlightDTO->fillCompany($company);
        $this->assertInstanceOf(Company::class, $newCompany);
        $this->assertEquals('Goldman Sachs Group', $newCompany->getName());
        $this->assertEquals('Germany', $newCompany->getCountry());
        $this->assertEquals('Hannover', $newCompany->getCity());
        $this->assertEquals('Main street 1 Mansion House', $newCompany->getAddress1());
        $this->assertEquals(0, $this->floodlightDTO->getNumberOfEmployees());
        $this->assertIsInt($this->floodlightDTO->getNumberOfEmployees());
    }
}
