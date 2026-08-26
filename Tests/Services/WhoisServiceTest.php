<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Services;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class WhoisServiceTest extends TestCase
{
    /**
     * @param array<mixed>|null $rdapFixture
     */
    private function createService(?array $rdapFixture): WhoisService
    {
        return new class($this->createStub(LoggerInterface::class), $rdapFixture) extends WhoisService {
            /**
             * @param array<mixed>|null $rdapFixture
             */
            public function __construct(LoggerInterface $mauticLogger, private readonly ?array $rdapFixture)
            {
                parent::__construct($mauticLogger);
            }

            protected function fetchRdap(string $ip): ?array
            {
                return $this->rdapFixture;
            }
        };
    }

    public function testGetWhoisDataReturnsExpectedStructure(): void
    {
        $rdapFixture = [
            'name'     => 'Apple Inc.',
            'entities' => [
                [
                    'vcardArray' => [
                        'vcard',
                        [
                            ['version', [], 'text', '4.0'],
                            ['fn', [], 'text', 'Apple Inc.'],
                            ['email', [], 'text', 'admin@group.apple.com'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->createService($rdapFixture)->getWhoisData('17.17.17.17');

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('company_domain', $result);
        $this->assertEquals('group.apple.com', $result['company_domain']);
        $this->assertArrayHasKey('company_name', $result);
        $this->assertEquals('Apple Inc.', $result['company_name']);
        $this->assertArrayHasKey('is_isp', $result);
        $this->assertArrayHasKey('is_dialup', $result);
        $this->assertIsBool($result['is_isp']);
        $this->assertFalse($result['is_dialup']);
    }

    public function testGetWhoisDataReturnsEmptyArrayForInvalidIp(): void
    {
        $result = $this->createService(null)->getWhoisData('not-an-ip');
        $this->assertSame([], $result);
    }

    public function testGetWhoisDataReturnsEmptyArrayWhenRdapLookupFails(): void
    {
        $result = $this->createService(null)->getWhoisData('17.17.17.17');
        $this->assertSame([], $result);
    }
}
