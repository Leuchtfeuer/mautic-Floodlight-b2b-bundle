<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Entity;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;

final class LeadDomainsTest extends MauticMysqlTestCase
{
    public const TABLE_NAME = 'test_f4b_lead_ip_lookups';

    private FloodlightLeadDomains $leadDomain;

    protected function setUp(): void
    {
        parent::setUp();
        $leadDomain = new FloodlightLeadDomains();
        $leadDomain->setDomain('test');
        $leadDomain->setCount(1);
        $leadDomain->setDateAdded(new \DateTime());
        $leadDomain->setDateModified(new \DateTime());
        $leadDomain->setIpHash('local');
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
        $tables = $this->em->getConnection()->createSchemaManager()->listTableNames();
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
        $this->assertSame('test', $this->leadDomain->getDomain());
    }

    public function testGetCount(): void
    {
        $this->assertSame(1, $this->leadDomain->getCount());
    }

    public function testGetDateAdded(): void
    {
        $this->assertLessThanOrEqual(new \DateTime(), $this->leadDomain->getDateAdded());
    }

    public function testGetDateModified(): void
    {
        $this->assertInstanceOf(\DateTime::class, $this->leadDomain->getDateModified());
    }

    public function testSetLead(): void
    {
        $leadDomain = new FloodlightLeadDomains();
        $leadDomain->setContact($this->createStub(Lead::class));
        $this->assertInstanceOf(Lead::class, $leadDomain->getContact());
    }

    public function testSetDomain(): void
    {
        $this->leadDomain->setDomain('test2');
        $this->assertSame('test2', $this->leadDomain->getDomain());
    }

    public function testSetCount(): void
    {
        $this->leadDomain->setCount(2);
        $this->assertSame(2, $this->leadDomain->getCount());
    }

    public function testSetDateAdded(): void
    {
        $dateAdded = new \DateTime();
        $this->leadDomain->setDateAdded($dateAdded);
        $this->assertSame($dateAdded, $this->leadDomain->getDateAdded());
    }

    public function testSetDateModified(): void
    {
        $this->leadDomain->setDateModified(new \DateTime());
        $this->assertInstanceOf(\DateTime::class, $this->leadDomain->getDateModified());
    }

    public function testAddCount(): void
    {
        $this->leadDomain->addCount();
        $this->assertSame(2, $this->leadDomain->getCount());
    }
}
