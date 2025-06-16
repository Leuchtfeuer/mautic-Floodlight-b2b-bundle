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
        $this->assertTrue($domainUtility->hasMoreThanFourNumbers('123.23'));
        $this->assertFalse($domainUtility->hasMoreThanFourNumbers('google.com'));
        $this->assertTrue($domainUtility->hasMoreThanFourNumbers('127.0.0.1'));
    }
}