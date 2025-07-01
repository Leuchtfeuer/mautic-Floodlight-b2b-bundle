<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use GuzzleHttp\Exception\ClientException;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\Credentials;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ValidationCredentials;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class FloodlightService implements FloodlightServiceInterface
{
    public const FLOODLIGHT_VERSION = 'v1';

    public function __construct(private ApiClient $client, private LoggerInterface $logger, private string $floodlightApiUrl = 'https://api-dev.floodlight-b2b.com')
    {
    }

    public function getAPIClient(): ApiClient
    {
        return $this->client;
    }

    /**
     * @return mixed[]|false
     */
    public function get(string $ip): array|bool
    {
        $response   = $this->getCompanyByIp($ip);

        // Check if don't have response
        if (false === $response) {
            return false;
        }

        return $this->handleResult($response);
    }

    /**
     * @return array<string,string|int>
     */
    private function handleResult(ResponseInterface $response): array
    {
        $result = [
            'statusRequestCode' => 0,
        ];

        $statusCode = $response->getStatusCode();

        if (200 === $statusCode || 202 === $statusCode || 404 === $statusCode) {
            $content = $response->getBody()->getContents();

            $decodedResult = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (empty($decodedResult)) {
                $decodedResult = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
                $decodedResult = (array) $decodedResult;
            }

            if (is_array($decodedResult)) {
                $result = $decodedResult;
            }
        }

        $result['statusRequestCode'] = $statusCode;

        return $result;
    }

    private function makeRequest(string $url): ResponseInterface|false
    {
        try {
            $myClient = $this->client->getClient();

            return $this->client->getClient()->request('GET', $url);
        } catch (ClientException $e) {
            if ($e->hasResponse()) {
                $this->logger->error('Url client get : '.$url);
                $this->logger->error('FloodlightService getCompanyByIp fail: '.$e->getMessage());

                return $e->getResponse();
            }
        }

        return false;
    }

    private function getCompanyByIp(string $ip): ResponseInterface|false
    {
        $url = sprintf('%s/%s/companies/ip/%s', $this->floodlightApiUrl, self::FLOODLIGHT_VERSION, $ip);

        return $this->makeRequest($url);
    }

    /**
     * @return mixed[]|false
     */
    public function getByDomain(string $domain): bool|array
    {
        $response   = $this->getCompanyByDomain($domain);
        // Check if don't have response

        if (false === $response) {
            return false;
        }

        return $this->handleResult($response);
    }

    private function getCompanyByDomain(string $domainName): ResponseInterface|false
    {
        $url = sprintf('%s/%s/companies/domain/%s', $this->floodlightApiUrl, self::FLOODLIGHT_VERSION, $domainName);

        return $this->makeRequest($url);
    }

    /**
     * Prüft den Status der API-Autorisierung.
     */
    public function getAuthorizationStatus(): ResponseInterface|false
    {
        $url = sprintf('%s/%s/auth/usage', $this->floodlightApiUrl, self::FLOODLIGHT_VERSION);

        return $this->makeRequest($url);
    }

    /**
     * Verarbeitet das Ergebnis der API-Key-Validierung - vereinfachte Version.
     */
    private function handleAuthorizationResult(ResponseInterface $result): JsonResponse
    {
        $statusCode = $result->getStatusCode();

        if (200 === $statusCode) {
            // Status 200 = Credentials sind gültig, egal was der Body sagt
            return new JsonResponse(['message' => 'Valid credentials'], Response::HTTP_OK);
        }

        // Alles andere = Credentials sind ungültig
        return new JsonResponse(['message' => 'Your credentials are invalid'], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Prüft den Status der API-Autorisierung und gibt eine lesbare Meldung zurück.
     */
    public function validateApiKey(): JsonResponse
    {
        $result = $this->getAuthorizationStatus();

        if (false === $result) {
            return new JsonResponse(['message' => 'Could not connect to API for validation.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->handleAuthorizationResult($result);
    }

    /**
     * Validiert API-Credentials direkt und gibt nur true/false zurück
     * Wird vom Symfony-Validator verwendet für Form-Daten (VOR dem Speichern).
     */
    public function validateApiCredentials(string $accessKeyId, string $secretKey): bool
    {
        try {
            // Erstelle ValidationCredentials lokal - das ist für diese eine Verwendung OK
            $validationCredentials = new ValidationCredentials();
            $validationCredentials->setApiAccessKeyId($accessKeyId);
            $validationCredentials->setApiSecretKey($secretKey);

            // Setze Credentials auf den ApiClient
            $this->client->setCredentials($validationCredentials);

            // Verwende die bestehende validateApiKey Methode
            $jsonResponse = $this->validateApiKey();

            // Prüfe den HTTP-Status der JsonResponse
            return 200 === $jsonResponse->getStatusCode() || 429 === $jsonResponse->getStatusCode();
        } catch (\Exception $e) {
            $this->logger->error('FloodlightService validateApiCredentials failed: '.$e->getMessage());

            return false;
        }
    }
}
