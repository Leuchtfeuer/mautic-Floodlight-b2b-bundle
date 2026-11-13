<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mautic\IntegrationsBundle\Exception\PluginNotConfiguredException;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\CredentialsValidationResult;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

final class FloodlightServiceTest extends TestCase
{
    public function testSuccessTwoStatusCodeOne200(): void
    {
        $client            = self::createStub(ApiClient::class);
        $responseInterface = self::createStub(ResponseInterface::class);
        $guzzleClient      = self::createStub(Client::class);
        $stream            = self::createStub(StreamInterface::class);
        $logger            = self::createStub(LoggerInterface::class);
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
        $client            = self::createStub(ApiClient::class);
        $responseInterface = self::createStub(ResponseInterface::class);
        $guzzleClient      = self::createStub(Client::class);
        $logger            = self::createStub(LoggerInterface::class);
        $stream            = self::createStub(StreamInterface::class);
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
        $client            = self::createStub(ApiClient::class);
        $responseInterface = self::createStub(ResponseInterface::class);
        $guzzleClient      = self::createStub(Client::class);
        $logger            = self::createStub(LoggerInterface::class);
        $stream            = self::createStub(StreamInterface::class);

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
        $client            = self::createStub(ApiClient::class);
        $responseInterface = self::createStub(ResponseInterface::class);
        $guzzleClient      = self::createStub(Client::class);
        $stream            = self::createStub(StreamInterface::class);
        $logger            = self::createStub(LoggerInterface::class);
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

    #[\PHPUnit\Framework\Attributes\DataProvider('credentialsValidationProvider')]
    public function testValidateApiCredentials(Response|\Throwable $outcome, CredentialsValidationResult $expected): void
    {
        $guzzleClient = self::createStub(Client::class);
        if ($outcome instanceof \Throwable) {
            $guzzleClient->method('request')->willThrowException($outcome);
        } else {
            $guzzleClient->method('request')->willReturn($outcome);
        }

        $client = self::createStub(ApiClient::class);
        $client->method('getClient')->willReturn($guzzleClient);

        $service = new FloodlightService($client, self::createStub(LoggerInterface::class));

        $this->assertSame($expected, $service->validateApiCredentials('key', 'secret'));
    }

    public static function credentialsValidationProvider(): \Generator
    {
        $request = new Request('GET', '/v1/auth/usage');

        yield 'valid' => [new Response(200), CredentialsValidationResult::Valid];
        yield 'unauthorized' => [new ClientException('Unauthorized', $request, new Response(401)), CredentialsValidationResult::Invalid];
        yield 'forbidden' => [new ClientException('Forbidden', $request, new Response(403)), CredentialsValidationResult::Invalid];
        yield 'too many requests' => [new ClientException('Too many requests', $request, new Response(429)), CredentialsValidationResult::Unverifiable];
        yield 'server error' => [new ServerException('Server error', $request, new Response(500)), CredentialsValidationResult::Unverifiable];
        yield 'no connection' => [new ConnectException('Connection refused', $request), CredentialsValidationResult::Unverifiable];
    }

    public function testValidateApiCredentialsWithMissingSecretIsInvalid(): void
    {
        $client = self::createStub(ApiClient::class);
        $client->method('getClient')->willThrowException(new PluginNotConfiguredException('API key is missing'));

        $service = new FloodlightService($client, self::createStub(LoggerInterface::class));

        $this->assertSame(CredentialsValidationResult::Invalid, $service->validateApiCredentials('key', ''));
    }
}
