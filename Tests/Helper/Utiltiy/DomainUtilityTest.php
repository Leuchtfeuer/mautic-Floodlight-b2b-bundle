<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Helper\Utiltiy;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisPyService;
use PHPUnit\Framework\TestCase;

class DomainUtilityTest extends TestCase
{
    public function testIsIp(): void
    {
        $leadDomainsModel = $this->createMock(FloodlightLeadDomainsModel::class);
        $whoisPyService = $this->createMock(WhoisPyService::class);

        $domainUtility = new DomainUtility($leadDomainsModel, $whoisPyService);
        $this->assertTrue($domainUtility->isIp('123.23'));
        $this->assertFalse($domainUtility->isIp('google.com'));
        $this->assertTrue($domainUtility->isIp('127.0.0.1'));
    }
}