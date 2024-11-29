<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Entity;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;

class LeadDomainsTest extends MauticMysqlTestCase
{
    public const TABLE_NAME = 'test_f4b_lead_domains';

    private $leadDomain;

    protected function setUp(): void
    {
        parent::setUp();
        $leadDomain = new \MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains();
        $leadDomain->setDomain('test');
        $leadDomain->setCount(1);
        $leadDomain->setDateAdded(new \DateTime());
        $leadDomain->setDateModified(new \DateTime());
        $lead = new Lead();
        $lead->setId(1);
        $this->em->persist($lead);
        $this->em->flush();
        $leadDomain->setContact($lead);
        $this->em->persist($leadDomain);
        $this->em->flush();
        $this->leadDomain = $leadDomain;
    }

    public function testIfTableIsCreated(): void
    {
        /**
         * Check if table was created with especified columns and joins.
         */
        $tables = $this->em->getConnection()->getSchemaManager()->listTableNames();
        $this->assertContains(self::TABLE_NAME, $tables);
    }

    public function testGetId(): void
    {
        $this->assertIsNumeric($this->leadDomain->getId());
    }

    public function testGetLead(): void
    {
        $this->assertInstanceOf(Lead::class, $this->leadDomain->getContact());
    }

    public function testGetDomain(): void
    {
        $this->assertEquals('test', $this->leadDomain->getDomain());
    }

    public function testGetCount(): void
    {
        $this->assertEquals(1, $this->leadDomain->getCount());
    }

    public function testGetDateAdded(): void
    {
        $this->assertInstanceOf(\DateTime::class, $this->leadDomain->getDateAdded());
    }

    public function testGetDateModified(): void
    {
        $this->assertInstanceOf(\DateTime::class, $this->leadDomain->getDateModified());
    }

    public function testSetLead(): void
    {
        $leadDomain = new \MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains();
        $leadDomain->setContact($this->createMock(Lead::class));
        $this->assertInstanceOf(Lead::class, $leadDomain->getContact());
    }

    public function testSetDomain(): void
    {
        $this->leadDomain->setDomain('test2');
        $this->assertEquals('test2', $this->leadDomain->getDomain());
    }

    public function testSetCount(): void
    {
        $this->leadDomain->setCount(2);
        $this->assertEquals(2, $this->leadDomain->getCount());
    }

    public function testSetDateAdded()
    {
        $this->leadDomain->setDateAdded(new \DateTime());
        $this->assertInstanceOf(\DateTime::class, $this->leadDomain->getDateAdded());
    }

    public function testSetDateModified(): void
    {
        $this->leadDomain->setDateModified(new \DateTime());
        $this->assertInstanceOf(\DateTime::class, $this->leadDomain->getDateModified());
    }

    public function testAddCount(): void
    {
        $this->leadDomain->addCount();
        $this->assertEquals(2, $this->leadDomain->getCount());
    }
}
