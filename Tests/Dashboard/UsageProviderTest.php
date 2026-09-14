<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Dashboard;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageException;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageProvider;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UsageProviderTest extends TestCase
{
    private ApiClient&MockObject $apiClient;

    private Client&MockObject $guzzle;

    private Config&MockObject $config;

    private LoggerInterface&MockObject $logger;

    private UsageProvider $provider;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(ApiClient::class);
        $this->guzzle    = $this->createMock(Client::class);
        $this->config    = $this->createMock(Config::class);
        $this->logger    = $this->createMock(LoggerInterface::class);

        $this->apiClient->method('getClient')->willReturn($this->guzzle);

        $this->provider = new UsageProvider(
            $this->apiClient,
            $this->config,
            $this->logger,
            'https://api.example.com',
        );
    }

    private function configureAsReady(): void
    {
        $this->config->method('isPublished')->willReturn(true);
        $this->config->method('isConfigured')->willReturn(true);
    }

    public function testMapsValidResponse(): void
    {
        $this->configureAsReady();
        $body = json_encode([
            'remaining'    => 134,
            'monthlyLimit' => 500,
            'buffer'       => 200,
            'nextRenewal'  => 'Thu, 14 Aug 2025 22:00:00 GMT',
            'plan'         => 'Professional',
        ]);
        $this->guzzle->expects(self::once())
            ->method('request')
            ->with('GET', 'https://api.example.com/v1/auth/usage', [
                'connect_timeout' => 2,
                'timeout'         => 5,
            ])
            ->willReturn(new Response(200, [], $body));

        $usage = $this->provider->getUsage();

        self::assertSame(134, $usage->getRemaining());
        self::assertSame(500, $usage->getMonthlyLimit());
        self::assertSame(200, $usage->getBuffer());
        self::assertSame('2025-08-14', $usage->getNextRenewal()->format('Y-m-d'));
        self::assertSame('Professional', $usage->getPlan());
    }

    public function testRejectsMissingPlan(): void
    {
        $this->configureAsReady();
        $body = json_encode([
            'remaining'    => 134,
            'monthlyLimit' => 500,
            'buffer'       => 200,
            'nextRenewal'  => 'Thu, 14 Aug 2025 22:00:00 GMT',
        ]);
        $this->guzzle->method('request')->willReturn(new Response(200, [], $body));

        $this->assertUsageCategory(UsageException::UNAVAILABLE);
    }

    public function testRejectsMissingBuffer(): void
    {
        $this->configureAsReady();
        $body = json_encode([
            'remaining'    => 1000,
            'monthlyLimit' => 1000,
            'nextRenewal'  => 'Sat, 31 May 2025 22:00:00 GMT',
        ]);
        $this->guzzle->method('request')->willReturn(new Response(200, [], $body));

        $this->assertUsageCategory(UsageException::UNAVAILABLE);
    }

    public function testNotConfiguredWhenIntegrationDisabled(): void
    {
        $this->config->method('isPublished')->willReturn(false);
        $this->config->expects(self::never())->method('isConfigured');

        // The client must not be called when configuration is incomplete.
        $this->guzzle->expects(self::never())->method('request');

        $this->assertUsageCategory(UsageException::NOT_CONFIGURED);
    }

    public function testNotConfiguredWhenCredentialsMissing(): void
    {
        $this->config->method('isPublished')->willReturn(true);
        $this->config->method('isConfigured')->willReturn(false);
        $this->guzzle->expects(self::never())->method('request');

        $this->assertUsageCategory(UsageException::NOT_CONFIGURED);
    }

    public function testRejectsMalformedJson(): void
    {
        $this->configureAsReady();
        $this->guzzle->method('request')->willReturn(new Response(200, [], '{not-json'));

        $this->assertUsageCategory(UsageException::UNAVAILABLE);
    }

    public function testRejectsMissingField(): void
    {
        $this->configureAsReady();
        $body = json_encode(['monthlyLimit' => 500, 'nextRenewal' => 'Thu, 14 Aug 2025 22:00:00 GMT']);
        $this->guzzle->method('request')->willReturn(new Response(200, [], $body));

        $this->assertUsageCategory(UsageException::UNAVAILABLE);
    }

    public function testRejectsNegativeRemaining(): void
    {
        $this->configureAsReady();
        $body = json_encode(['remaining' => -1, 'monthlyLimit' => 500, 'nextRenewal' => 'Thu, 14 Aug 2025 22:00:00 GMT']);
        $this->guzzle->method('request')->willReturn(new Response(200, [], $body));

        $this->assertUsageCategory(UsageException::UNAVAILABLE);
    }

    public function testRejectsNonPositiveLimit(): void
    {
        $this->configureAsReady();
        $body = json_encode(['remaining' => 10, 'monthlyLimit' => 0, 'nextRenewal' => 'Thu, 14 Aug 2025 22:00:00 GMT']);
        $this->guzzle->method('request')->willReturn(new Response(200, [], $body));

        $this->assertUsageCategory(UsageException::UNAVAILABLE);
    }

    public function testRejectsInvalidRenewalDate(): void
    {
        $this->configureAsReady();
        $body = json_encode(['remaining' => 10, 'monthlyLimit' => 500, 'nextRenewal' => 'not-a-date']);
        $this->guzzle->method('request')->willReturn(new Response(200, [], $body));

        $this->assertUsageCategory(UsageException::UNAVAILABLE);
    }

    public function testMaps401ToInvalidCredentials(): void
    {
        $this->configureAsReady();
        $this->guzzle->method('request')->willThrowException(
            new ClientException('Unauthorized', new Request('GET', '/'), new Response(401))
        );

        $this->assertUsageCategory(UsageException::INVALID_CREDENTIALS);
    }

    public function testMaps403ToInvalidCredentials(): void
    {
        $this->configureAsReady();
        $this->guzzle->method('request')->willThrowException(
            new ClientException('Forbidden', new Request('GET', '/'), new Response(403))
        );

        $this->assertUsageCategory(UsageException::INVALID_CREDENTIALS);
    }

    public function testMaps429ToRateLimited(): void
    {
        $this->configureAsReady();
        $this->guzzle->method('request')->willThrowException(
            new ClientException('Too many requests', new Request('GET', '/'), new Response(429))
        );

        $this->assertUsageCategory(UsageException::RATE_LIMITED);
    }

    public function testMapsOtherStatusToUnavailable(): void
    {
        $this->configureAsReady();
        $this->guzzle->method('request')->willThrowException(
            new ServerException('Server error', new Request('GET', '/'), new Response(500))
        );

        $this->assertUsageCategory(UsageException::UNAVAILABLE);
    }

    public function testMapsTransportErrorToUnavailable(): void
    {
        $this->configureAsReady();
        $this->guzzle->method('request')->willThrowException(
            new ConnectException('Connection refused', new Request('GET', '/'))
        );

        $this->assertUsageCategory(UsageException::UNAVAILABLE);
    }

    private function assertUsageCategory(string $expectedCategory): void
    {
        try {
            $this->provider->getUsage();
            self::fail('Expected UsageException was not thrown.');
        } catch (UsageException $e) {
            self::assertSame($expectedCategory, $e->getCategory());
        }
    }
}
