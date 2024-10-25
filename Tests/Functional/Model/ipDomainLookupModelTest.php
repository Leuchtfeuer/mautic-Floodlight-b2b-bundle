<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Model;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightIpDomainLookups;

class ipDomainLookupModelTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testSuccessCheckIsNewIp()
    {
        $ip     = '200.11.234.12';
        $domain = 'leuchtfeuer.com';
        $type   = 'unknown';
        $date   = new \DateTime();
        $entity = new FloodlightIpDomainLookups();
        $entity->setIp($ip);
        $entity->setDomain($domain);
        $entity->setType($type);
        $entity->setDateAdded($date);
        $this->em->persist($entity);
        $this->em->flush();
        $isNewIpFalse = $this->em->getRepository(FloodlightIpDomainLookups::class)->isNew($ip);
        $isNewIpTrue  = $this->em->getRepository(FloodlightIpDomainLookups::class)->isNew('220.111.33.123');
        $this->assertFalse($isNewIpFalse);
        $this->assertTrue($isNewIpTrue);
    }
}
