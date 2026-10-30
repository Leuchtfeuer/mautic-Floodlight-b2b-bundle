<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Services;

use GuzzleHttp\Client;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

final class FloodlightServiceTest extends TestCase
{
    public function testSuccessTwoStatusCodeOne200(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $stream            = $this->createMock(StreamInterface::class);
        $logger            = $this->createStub(LoggerInterface::class);
        $stream
            ->method('getContents')
            ->willReturn('[{"foo": "bar"}]');
        $guzzleClient
            ->method('request')
            ->willReturn($responseInterface);
        $client
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface
            ->method('getStatusCode')
            ->willReturn(200);
        $responseInterface
            ->method('getBody')
            ->willReturn($stream);

        $service = new FloodlightService($client, $logger);
        $result  = $service->get('1.2.3.4');
        $this->assertSame([['foo' => 'bar'], 'statusRequestCode' => 200], $result);
        $this->assertIsArray($result);
    }

    public function testFailThree202Response(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $logger            = $this->createStub(LoggerInterface::class);
        $stream            = $this->createMock(StreamInterface::class);
        $stream
            ->method('getContents')
            ->willReturn('[{"foo": "bar"}]');
        $guzzleClient
            ->method('request')
            ->willReturn($responseInterface);
        $client
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface
            ->method('getStatusCode')
            ->willReturn(202);
        $responseInterface
            ->method('getBody')
            ->willReturn($stream);

        $service = new FloodlightService($client, $logger);
        $result  = $service->get('12.1.1.3');
        $this->assertIsArray($result);
        $this->assertIsNotBool($result['statusRequestCode']);
        $this->assertSame(202, $result['statusRequestCode']);
    }

    public function testFailStatusCode404(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $logger            = $this->createStub(LoggerInterface::class);
        $stream            = $this->createMock(StreamInterface::class);

        $guzzleClient
            ->method('request')
            ->willReturn($responseInterface);
        $client
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface
            ->method('getStatusCode')
            ->willReturn(404);
        $responseInterface
            ->method('getBody')
            ->willReturn($stream);
        $stream
            ->method('getContents')
            ->willReturn('[{"foo": "bar"}]');

        $service = new FloodlightService($client, $logger);

        $result  = $service->get('1.2.3.4');
        $this->assertIsArray($result);
        $this->assertIsNotBool($result['statusRequestCode']);
        $this->assertSame([['foo' => 'bar'], 'statusRequestCode' => 404], $result);
    }

    public function testSuccess200FirstTry(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $stream            = $this->createMock(StreamInterface::class);
        $logger            = $this->createStub(LoggerInterface::class);
        $stream
            ->method('getContents')
            ->willReturn('[{"foo": "bar"}]');
        $guzzleClient
            ->method('request')
            ->willReturn($responseInterface);
        $client
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface
            ->method('getStatusCode')
            ->willReturn(200);
        $responseInterface
            ->method('getBody')
            ->willReturn($stream);

        $service = new FloodlightService($client, $logger);
        $result  = $service->get('1.2.3.4');
        $this->assertSame([['foo' => 'bar'], 'statusRequestCode' => 200], $result);
        $this->assertIsArray($result);
    }

    public function testGetLogoSuccess(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $stream            = $this->createMock(StreamInterface::class);
        $logger            = $this->createStub(LoggerInterface::class);
        $stream
            ->method('getContents')
            ->willReturn('binary-image-data');
        $guzzleClient
            ->method('request')
            ->willReturn($responseInterface);
        $client
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface
            ->method('getStatusCode')
            ->willReturn(200);
        $responseInterface
            ->method('getBody')
            ->willReturn($stream);
        $responseInterface
            ->method('getHeaderLine')
            ->with('Content-Type')
            ->willReturn('image/png');

        $service = new FloodlightService($client, $logger);
        $result  = $service->getLogo('https://api.floodlight-b2b.com/v1/companies/logos/abc');
        $this->assertSame(['content' => 'binary-image-data', 'contentType' => 'image/png'], $result);
    }

    public function testGetLogoReturnsNullOnNon200(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $logger            = $this->createStub(LoggerInterface::class);
        $guzzleClient
            ->method('request')
            ->willReturn($responseInterface);
        $client
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface
            ->method('getStatusCode')
            ->willReturn(404);

        $service = new FloodlightService($client, $logger);
        $this->assertNull($service->getLogo('https://api.floodlight-b2b.com/v1/companies/logos/abc'));
    }

    public function testGetLogoReturnsNullAndLogsOnException(): void
    {
        $client       = $this->createMock(ApiClient::class);
        $guzzleClient = $this->createMock(Client::class);
        $logger       = $this->createMock(LoggerInterface::class);
        $guzzleClient
            ->method('request')
            ->willThrowException(new \RuntimeException('401 Unauthorized'));
        $client
            ->method('getClient')
            ->willReturn($guzzleClient);
        $logger->expects($this->once())
            ->method('error');

        $service = new FloodlightService($client, $logger);
        $this->assertNull($service->getLogo('https://api.floodlight-b2b.com/v1/companies/logos/abc'));
    }

    /**
     * @return \Iterator<string, array{string, bool}>
     */
    public static function logoOriginProvider(): \Iterator
    {
        yield 'default host' => ['https://api.floodlight-b2b.com/logo/123.png', true];
        yield 'configured host' => ['https://api-staging.floodlight-b2b.com/logo/123.png', true];
        yield 'no https' => ['http://api.floodlight-b2b.com/logo/123.png', false];
        yield 'foreign host' => ['https://evil.com/logo.png', false];
        yield 'host suffix attack' => ['https://api.floodlight-b2b.com.evil.com/logo.png', false];
    }

    #[DataProvider('logoOriginProvider')]
    public function testIsAllowedLogoOrigin(string $url, bool $expected): void
    {
        $service = new FloodlightService(
            $this->createStub(ApiClient::class),
            $this->createStub(LoggerInterface::class),
            'https://api-staging.floodlight-b2b.com'
        );

        $this->assertSame($expected, $service->isAllowedLogoOrigin($url));
    }

    public function testGetLogoSkipsForeignOriginWithoutRequest(): void
    {
        $client = $this->createMock(ApiClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $client->expects($this->never())
            ->method('getClient');
        $logger->expects($this->once())
            ->method('warning');

        $service = new FloodlightService($client, $logger);
        $this->assertNull($service->getLogo('https://evil.com/logo.png'));
    }
}
