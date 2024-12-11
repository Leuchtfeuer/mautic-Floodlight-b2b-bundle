<?php

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
        self::assertSame(202, $result['statusRequestCode']);
    }

    public function testFailStatusCode404(): void
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

        $result  = $service->get('1.2.3.4');
        self::assertSame(404, $result['statusRequestCode']);
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
}
