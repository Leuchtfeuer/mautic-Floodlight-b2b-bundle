<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Helper\Utiltiy;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\KeywordBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisPyService;
use PHPUnit\Framework\TestCase;

class DomainUtilityTest extends TestCase
{
    public function testIsIp(): void
    {
        $whoisPyService         = $this->createMock(WhoisPyService::class);
        $keywordBlacklistHelper = $this->createMock(KeywordBlacklistHelper::class);
        $domainBlacklistHelper  = $this->createMock(DomainBlacklistHelper::class);

        $domainUtility = new DomainUtility($whoisPyService, $keywordBlacklistHelper, $domainBlacklistHelper);
        $this->assertTrue($domainUtility->hasMoreThanFourNumbers('123.23'));
        $this->assertFalse($domainUtility->hasMoreThanFourNumbers('google.com'));
        $this->assertTrue($domainUtility->hasMoreThanFourNumbers('127.0.0.1'));
    }
}
