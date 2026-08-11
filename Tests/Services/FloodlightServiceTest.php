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

    /*
    public function testGetFloodlightKeyValidationStatusUnauthorized(): void
    {
        $client            = $this->createMock(ApiClient::class);
        $responseInterface = $this->createMock(ResponseInterface::class);
        $guzzleClient      = $this->createMock(Client::class);
        $logger            = $this->createMock(LoggerInterface::class);
        $stream            = $this->createMock(StreamInterface::class);

        // Mock für den Stream, um die JSON-Antwort zu liefern
        $stream->expects(self::any())
            ->method('getContents')
            ->willReturn('{"statusCode": 401, "timestamp": "2025-04-04T06:46:53.840Z", "message": "Unauthorized", "path": "/v1/auth/usage"}');

        $guzzleClient->expects(self::any())
            ->method('request')
            ->willReturn($responseInterface);

        $client->expects(self::any())
            ->method('getClient')
            ->willReturn($guzzleClient);

        $responseInterface->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(401);

        $responseInterface->expects(self::any())
            ->method('getBody')
            ->willReturn($stream);

        // Erstelle einen teilweisen Mock vom FloodlightService, der nur die handleResult-Methode überschreibt
        $service = $this->getMockBuilder(FloodlightService::class)
            ->setConstructorArgs([$client, $logger])
            ->onlyMethods(['handleResult'])
            ->getMock();

        // Konfiguriere den Mock, um das erwartete Array zurückzugeben
        $expectedResult = [
            'statusCode' => 401,
            'timestamp' => '2025-04-04T06:46:53.840Z',
            'message' => 'Unauthorized',
            'path' => '/v1/auth/usage',
            'statusRequestCode' => 401
        ];

        $service->expects(self::once())
            ->method('handleResult')
            ->willReturn($expectedResult);

        $result = $service->getAuthorizationStatus();

        self::assertSame($expectedResult, $result);
    }
        */

    /*
     * {
    "statusCode": 401,
    "timestamp": "2025-04-04T06:46:53.840Z",
    "message": "Unauthorized",
    "path": "/v1/auth/usage"
    }
    Valid:
    {
        "remaining": 996,
        "limit": 1000,
        "free": false,
        "next_renewal": "2025-04-30T22:00:00.000Z"
    }
    Exeeded:
    {
        "remaining": 0,
        "limit": 1000,
        "free": false,
        "next_renewal": "2025-04-30T22:00:00.000Z"
    }
     */
}
