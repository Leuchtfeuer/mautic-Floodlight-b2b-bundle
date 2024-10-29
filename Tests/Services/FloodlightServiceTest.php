<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Services;

use GuzzleHttp\Client;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class FloodlightServiceTest extends TestCase
{
    public function testSuccessTwoStatusCode202AndOne200(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $stream            = $this->createMock(StreamInterface::class);
        $stream->expects(self::once())
            ->method('getContents')
            ->willReturn('[{"foo": "bar"}]');
        $guzzleClient->expects(self::exactly(3))
            ->method('request')
            ->willReturn($responseInterface);
        $client->expects(self::exactly(3))
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface->expects(self::exactly(3))
            ->method('getStatusCode')
            ->willReturnOnConsecutiveCalls(202, 202, 200);
        $responseInterface->expects(self::once())
            ->method('getBody')
            ->willReturn($stream);

        $service = new FloodlightService($client);
        $result  = $service->get('1.2.3.4');
        self::assertSame([['foo' => 'bar']], $result);
        self::assertIsArray($result);
    }

    public function testFailThree202Response(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $guzzleClient->expects(self::exactly(3))
            ->method('request')
            ->willReturn($responseInterface);
        $client->expects(self::exactly(3))
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface->expects(self::exactly(3))
            ->method('getStatusCode')
            ->willReturn(202);

        $service = new FloodlightService($client);
        $result  = $service->get('12.1.1.3');
        self::assertFalse($result);
        self::assertIsBool($result);
    }

    public function testFailStatusCode404(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);

        $guzzleClient->expects(self::once())
            ->method('request')
            ->willReturn($responseInterface);
        $client->expects(self::once())
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface->expects(self::once())
            ->method('getStatusCode')
            ->willReturn(404);

        $service = new FloodlightService($client);
        $result  = $service->get('1.2.3.4');
        self::assertFalse($result);
        self::assertIsBool($result);
    }

    public function testSuccess200FirstTry(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $stream            = $this->createMock(StreamInterface::class);
        $stream->expects(self::once())
            ->method('getContents')
            ->willReturn('[{"foo": "bar"}]');
        $guzzleClient->expects(self::once())
            ->method('request')
            ->willReturn($responseInterface);
        $client->expects(self::once())
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface->expects(self::once())
            ->method('getStatusCode')
            ->willReturn(200);
        $responseInterface->expects(self::once())
            ->method('getBody')
            ->willReturn($stream);

        $service = new FloodlightService($client);
        $result  = $service->get('1.2.3.4');
        self::assertSame([['foo' => 'bar']], $result);
        self::assertIsArray($result);
    }

    public function testFailStatusCode202And404(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $guzzleClient->expects(self::exactly(2))
            ->method('request')
            ->willReturn($responseInterface);
        $client->expects(self::exactly(2))
            ->method('getClient')
            ->willReturn($guzzleClient);
        $responseInterface->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturnOnConsecutiveCalls(202, 404);

        $service = new FloodlightService($client);
        $result  = $service->get('1.2.3.4');
        self::assertFalse($result);
        self::assertIsBool($result);
    }
}
