<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Services;

use GuzzleHttp\Client;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

class FloodlightServiceTest extends TestCase
{
    public function testSuccessTwoStatusCodeOne200(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $stream            = $this->createMock(StreamInterface::class);
        $logger            = $this->createMock(LoggerInterface::class);
        $stream->expects(self::any())
            ->method('getContents')
            ->willReturn('[{"foo": "bar"}]');
        $guzzleClient->expects(self::any())
            ->method('request')
            ->willReturn($responseInterface);
        $client->expects(self::any())
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface->expects(self::any())
            ->method('getStatusCode')
            ->willReturnOnConsecutiveCalls(200);
        $responseInterface->expects(self::any())
            ->method('getBody')
            ->willReturn($stream);

        $service = new FloodlightService($client, $logger);
        $result  = $service->get('1.2.3.4');
        self::assertSame([['foo' => 'bar'], 'statusRequestCode' => 200], $result);
        self::assertIsArray($result);
    }

    public function testFailThree202Response(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $logger            = $this->createMock(LoggerInterface::class);
        $stream            = $this->createMock(StreamInterface::class);
        $stream->expects(self::any())
            ->method('getContents')
            ->willReturn('[{"foo": "bar"}]');
        $guzzleClient->expects(self::any())
            ->method('request')
            ->willReturn($responseInterface);
        $client->expects(self::any())
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface->expects(self::any())
            ->method('getStatusCode')
            ->willReturnOnConsecutiveCalls(202);
        $responseInterface->expects(self::any())
            ->method('getBody')
            ->willReturn($stream);

        $service = new FloodlightService($client, $logger);
        $result  = $service->get('12.1.1.3');
        $this->assertIsArray($result);
        $this->assertIsNotBool($result['statusRequestCode']);
        self::assertSame(202, $result['statusRequestCode']);
    }

    public function testFailStatusCode404(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $logger            = $this->createMock(LoggerInterface::class);
        $stream            = $this->createMock(StreamInterface::class);

        $guzzleClient->expects(self::any())
            ->method('request')
            ->willReturn($responseInterface);
        $client->expects(self::any())
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(404);
        $responseInterface->expects(self::any())
            ->method('getBody')
            ->willReturn($stream);
        $stream->expects(self::any())
            ->method('getContents')
            ->willReturn('[{"foo": "bar"}]');

        $service = new FloodlightService($client, $logger);

        $result  = $service->get('1.2.3.4');
        $this->assertIsArray($result);
        $this->assertIsNotBool($result['statusRequestCode']);
        self::assertSame([['foo' => 'bar'], 'statusRequestCode' => 404], $result);
    }

    public function testSuccess200FirstTry(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $stream            = $this->createMock(StreamInterface::class);
        $logger            = $this->createMock(LoggerInterface::class);
        $stream->expects(self::any())
            ->method('getContents')
            ->willReturn('[{"foo": "bar"}]');
        $guzzleClient->expects(self::any())
            ->method('request')
            ->willReturn($responseInterface);
        $client->expects(self::any())
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(200);
        $responseInterface->expects(self::any())
            ->method('getBody')
            ->willReturn($stream);

        $service = new FloodlightService($client, $logger);
        $result  = $service->get('1.2.3.4');
        self::assertSame([['foo' => 'bar'], 'statusRequestCode' => 200], $result);
        self::assertIsArray($result);
    }

    public function testGetLogoSuccess(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $stream            = $this->createMock(StreamInterface::class);
        $logger            = $this->createMock(LoggerInterface::class);
        $stream->expects(self::any())
            ->method('getContents')
            ->willReturn('binary-image-data');
        $guzzleClient->expects(self::any())
            ->method('request')
            ->willReturn($responseInterface);
        $client->expects(self::any())
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(200);
        $responseInterface->expects(self::any())
            ->method('getBody')
            ->willReturn($stream);
        $responseInterface->expects(self::any())
            ->method('getHeaderLine')
            ->with('Content-Type')
            ->willReturn('image/png');

        $service = new FloodlightService($client, $logger);
        $result  = $service->getLogo('https://api.floodlight-b2b.com/v1/companies/logos/abc');
        self::assertSame(['content' => 'binary-image-data', 'contentType' => 'image/png'], $result);
    }

    public function testGetLogoReturnsNullOnNon200(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $logger            = $this->createMock(LoggerInterface::class);
        $guzzleClient->expects(self::any())
            ->method('request')
            ->willReturn($responseInterface);
        $client->expects(self::any())
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(404);

        $service = new FloodlightService($client, $logger);
        self::assertNull($service->getLogo('https://api.floodlight-b2b.com/v1/companies/logos/abc'));
    }

    public function testGetLogoReturnsNullAndLogsOnException(): void
    {
        $client       = $this->createMock(ApiClient::class);
        $guzzleClient = $this->createMock(Client::class);
        $logger       = $this->createMock(LoggerInterface::class);
        $guzzleClient->expects(self::any())
            ->method('request')
            ->willThrowException(new \RuntimeException('401 Unauthorized'));
        $client->expects(self::any())
            ->method('getClient')
            ->willReturn($guzzleClient);
        $logger->expects(self::once())
            ->method('error');

        $service = new FloodlightService($client, $logger);
        self::assertNull($service->getLogo('https://api.floodlight-b2b.com/v1/companies/logos/abc'));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function logoOriginProvider(): array
    {
        return [
            'default host'       => ['https://api.floodlight-b2b.com/logo/123.png', true],
            'dev host'           => ['https://api-dev.floodlight-b2b.com/logo/123.png', true],
            'configured host'    => ['https://api-staging.floodlight-b2b.com/logo/123.png', true],
            'no https'           => ['http://api.floodlight-b2b.com/logo/123.png', false],
            'foreign host'       => ['https://evil.com/logo.png', false],
            'host suffix attack' => ['https://api.floodlight-b2b.com.evil.com/logo.png', false],
        ];
    }

    /**
     * @dataProvider logoOriginProvider
     */
    public function testIsAllowedLogoOrigin(string $url, bool $expected): void
    {
        $service = new FloodlightService(
            $this->createMock(ApiClient::class),
            $this->createMock(LoggerInterface::class),
            'https://api-staging.floodlight-b2b.com'
        );

        self::assertSame($expected, $service->isAllowedLogoOrigin($url));
    }

    public function testGetLogoSkipsForeignOriginWithoutRequest(): void
    {
        $client = $this->createMock(ApiClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $client->expects(self::never())
            ->method('getClient');
        $logger->expects(self::once())
            ->method('warning');

        $service = new FloodlightService($client, $logger);
        self::assertNull($service->getLogo('https://evil.com/logo.png'));
    }
}
