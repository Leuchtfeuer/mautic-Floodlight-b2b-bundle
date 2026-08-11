<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Services;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WhoisServiceTest extends TestCase
{
    private WhoisService $service;

    protected function setUp(): void
    {
        $this->service = new WhoisService($this->createMock(LoggerInterface::class));
    }

    public function testGetWhoisDataReturnsExpectedStructure(): void
    {
        $result = $this->service->getWhoisData('17.17.17.17');

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('company_domain', $result);
        $this->assertEquals($result['company_domain'], 'group.apple.com');
        $this->assertArrayHasKey('company_name', $result);
        $this->assertEquals($result['company_name'], 'Apple Inc.');
        $this->assertArrayHasKey('is_isp', $result);
        $this->assertArrayHasKey('is_dialup', $result);
        $this->assertIsBool($result['is_isp']);
        $this->assertFalse($result['is_dialup']);
    }

    public function testGetWhoisDataReturnsEmptyArrayForInvalidIp(): void
    {
        $result = $this->service->getWhoisData('not-an-ip');
        $this->assertSame([], $result);
    }
}
