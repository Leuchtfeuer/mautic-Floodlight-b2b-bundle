<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Entity;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;

class CompanyDomainsTest extends MauticMysqlTestCase
{
    public const TABLE_NAME = 'test_f4b_company_domains';

    private $companyDomain;

    public function testIfTableIsCreated()
    {
        /**
         * Check if table was created with especified columns and joins.
         */
        $tables = $this->em->getConnection()->getSchemaManager()->listTableNames();
        $this->assertContains(self::TABLE_NAME, $tables);
    }

    protected function setUp(): void
    {
        $domain = 'localhost';
        parent::setUp();
        $companyDomain = new \MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains();
        $companyDomain->setDomain($domain);
        $company = new \Mautic\LeadBundle\Entity\Company();
        $company->setCreatedBy();
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());
        $company->setName($domain);
        $this->em->persist($company);
        $this->em->flush();
        $companyDomain->setCompany($company);
        $companyDomain->setDateAdded(new \DateTime());
        $companyDomain->setDateModified(new \DateTime());
        $companyDomain->setIsPrimaryDomain(true);
        $this->em->persist($companyDomain);
        $this->em->flush();
        $this->companyDomain = $companyDomain;
    }

    public function testGetId(): void
    {
        $this->assertIsNumeric($this->companyDomain->getId());
    }

    public function testGetCompany(): void
    {
        $this->assertInstanceOf(\Mautic\LeadBundle\Entity\Company::class, $this->companyDomain->getCompany());
    }

    public function testGetDomain(): void
    {
        $this->assertEquals('localhost', $this->companyDomain->getDomain());
    }

    public function testGetDateAdded(): void
    {
        $this->assertInstanceOf(\DateTime::class, $this->companyDomain->getDateAdded());
    }

    public function testGetDateModified(): void
    {
        $this->assertInstanceOf(\DateTime::class, $this->companyDomain->getDateModified());
    }

    public function testIsPrimaryDomain(): void
    {
        $this->assertTrue($this->companyDomain->isPrimaryDomain());
    }

    public function testSetIsPrimaryDomain(): void
    {
        $this->companyDomain->setIsPrimaryDomain(false);
        $this->assertFalse($this->companyDomain->isPrimaryDomain());
    }

    public function testSetDateModified(): void
    {
        $this->companyDomain->setDateModified(new \DateTime());
        $this->assertInstanceOf(\DateTime::class, $this->companyDomain->getDateModified());
    }

    public function testSetDateAdded(): void
    {
        $this->companyDomain->setDateAdded(new \DateTime());
        $this->assertInstanceOf(\DateTime::class, $this->companyDomain->getDateAdded());
    }

    public function testSetDomain(): void
    {
        $this->companyDomain->setDomain('test');
        $this->assertEquals('test', $this->companyDomain->getDomain());
    }
}
