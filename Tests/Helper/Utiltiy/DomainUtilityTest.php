<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Helper\Utiltiy;

use Mautic\CoreBundle\Helper\PathsHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\KeywordBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DomainUtilityTest extends TestCase
{
    public function testIsIp(): void
    {
        $whoisService           = self::createStub(WhoisService::class);
        $keywordBlacklistHelper = self::createStub(KeywordBlacklistHelper::class);
        $domainBlacklistHelper  = self::createStub(DomainBlacklistHelper::class);
        $pathsHelper            = self::createStub(PathsHelper::class);
        $logger                 = self::createStub(LoggerInterface::class);

        $domainUtility = new DomainUtility($whoisService, $keywordBlacklistHelper, $domainBlacklistHelper, $pathsHelper, $logger);
        $this->assertTrue($domainUtility->hasMoreThanFourNumbers('123.23'));
        $this->assertFalse($domainUtility->hasMoreThanFourNumbers('google.com'));
        $this->assertTrue($domainUtility->hasMoreThanFourNumbers('127.0.0.1'));
    }
}
