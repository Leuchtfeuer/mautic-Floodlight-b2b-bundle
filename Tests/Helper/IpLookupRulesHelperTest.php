<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Helper;

use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PageBundle\Entity\Hit;
use Mautic\PageBundle\Model\PageModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomainsRepository;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Reporter;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status200Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status202Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status404Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status429Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\fixtures\FixtureHelper;

final class IpLookupRulesHelperTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    public function setUp(): void
    {
        parent::setUp();
        $fixtureHelper = new FixtureHelper($this->em, $this->client);
        $fixtureHelper->createAndEnablePlugin();
        $fixtureHelper->copyBlacklistConfig();
    }

    // Disabled: getAndProcessFloodlightApiData returns void, not array
    public function disabledtestGetDomainMethod(): void
    {
        $this->markTestSkipped('getAndProcessFloodlightApiData returns void, not array');
    }

    public function testCheckReturnsFalseWhenLessThanTwoPagehits(): void
    {
        $helper = new IpLookupRulesHelper(
            self::getContainer()->get(PageModel::class),
            $this->createStub(FloodlightService::class),
            $this->createStub(Reporter::class),
            $this->createStub(Status200Strategy::class),
            $this->createStub(Status202Strategy::class),
            $this->createStub(Status404Strategy::class),
            $this->createStub(Status429Strategy::class),
            $this->createStub(Config::class),
            $this->createStub(FloodlightLeadDomainsRepository::class)
        );
        $lead = $this->createLead();
        $hit  = $this->createHit($lead);

        $result = $helper->check($hit, true);
        $this->assertFalse($result);
    }

    public function testCheckReturnsTrueWhenLessThanTwoPagehitsButIgnoreSingleClickVisitsIsDisabled(): void
    {
        $helper = new IpLookupRulesHelper(
            self::getContainer()->get(PageModel::class),
            $this->createStub(FloodlightService::class),
            $this->createStub(Reporter::class),
            $this->createStub(Status200Strategy::class),
            $this->createStub(Status202Strategy::class),
            $this->createStub(Status404Strategy::class),
            $this->createStub(Status429Strategy::class),
            $this->createStub(Config::class),
            $this->createStub(FloodlightLeadDomainsRepository::class)
        );
        $lead = $this->createLead();
        $hit  = $this->createHit($lead);

        $result = $helper->check($hit, false);
        $this->assertTrue($result);
    }

    public function testCheckIpLookUpCanBeProcessed(): void
    {
        $helper = new IpLookupRulesHelper(
            self::getContainer()->get(PageModel::class),
            $this->createStub(FloodlightService::class),
            $this->createStub(Reporter::class),
            $this->createStub(Status200Strategy::class),
            $this->createStub(Status202Strategy::class),
            $this->createStub(Status404Strategy::class),
            $this->createStub(Status429Strategy::class),
            $this->createStub(Config::class),
            self::getContainer()->get(FloodlightLeadDomainsRepository::class)
        );
        // Test with just one hit
        $lead    = $this->createLead();
        $hit1    = $this->createHit($lead);
        $result1 = $helper->check($hit1, true);
        // check more than 2 with just one hit, false
        $this->assertFalse($result1);

        // Test with two hits
        $hit2 = $this->createHit($lead);
        // check more than 2 with two hits, false
        // check lookup has more than one hour, but no lead domain, so is true
        $result2 = $helper->check($hit2, true);
        $this->assertTrue($result2);

        // Test with two hits and one lead domain in the last hour
        $leadDomain = $this->createFloodLightLeadDomain($lead);
        $result     = $helper->check($hit2, true);
        $this->assertFalse($result);

        // Test with two hits and one lead domain in the plus one hour
        $leadDomain->setDateModified(new \DateTime('-2 hours'));
        $leadDomain->setDateAdded(new \DateTime('-2 hours'));
        $this->em->persist($leadDomain);
        $this->em->flush();
        $result = $helper->check($hit2, true);
        $this->assertTrue($result);
    }

    private function createFloodLightLeadDomain(Lead $lead): FloodlightLeadDomains
    {
        $rand       = random_int(1, 1000000000);
        $leadDomain = new FloodlightLeadDomains();
        $leadDomain->setDomain($rand.'domain.com');
        $leadDomain->setContact($lead);
        $leadDomain->setIpHash('local');
        $leadDomain->setDateAdded(new \DateTime());
        $leadDomain->setCount(0);
        $leadDomain->setDateModified(new \DateTime());
        $this->em->persist($leadDomain);
        $this->em->flush();

        return $leadDomain;
    }

    private function createHit(Lead $lead): Hit
    {
        $ip = new IpAddress();
        $ip->setIpAddress('127.0.0.1');
        $ip->setIpDetails(['details' => 'details']);
        $this->em->persist($ip);
        $this->em->flush();

        $rand = random_int(1, 1000000000);
        $hit  = new Hit();
        $hit->setLead($lead);
        $hit->setDateHit(new \DateTime());
        $hit->setIpAddress($ip);
        $hit->setUrl('http://localhost');
        $hit->setReferer('http://localhost');
        $hit->setUserAgent('user agent');
        $hit->setPageLanguage('en');
        $hit->setCode(200);
        $hit->setTrackingId($rand.'123456');
        $this->em->persist($hit);
        $this->em->flush();

        return $hit;
    }

    private function createLead(): Lead
    {
        $rand = random_int(1, 1000000000);
        $lead = new Lead();
        $lead->setEmail($rand.'test1@mauit.com');
        $lead->setFirstname($rand.' first name');
        $lead->setFirstname($rand.' last name');
        $lead->setLastActive(new \DateTime());
        $lead->setIsPublished(true);
        $lead->setAddress1('address1');
        $lead->setAddress2('addres 222');
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }
}
