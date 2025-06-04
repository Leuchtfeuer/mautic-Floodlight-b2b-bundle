<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use GuzzleHttp\Exception\ClientException;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\Credentials;
use Symfony\Component\HttpFoundation\Response;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Mautic\IntegrationsBundle\Auth\Provider\ApiKey\HttpFactory;

class FloodlightService implements FloodlightServiceInterface
{
    private const FLOODLIGHT_API  = 'https://api.floodlight-b2b.com';

    public function __construct(
        private ApiClient $client,
        private LoggerInterface $logger
    ) {
    }

    public function getAPIClient(): ApiClient
    {
        return $this->client;
    }

    public const FLOODLIGHT_VERSION = 'v1';

    /**
     * @return array<mixed>|bool
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
        $result     = [
            'statusRequestCode' => 0,
        ];

        $statusCode = $response->getStatusCode();
        $body = $response->getBody();

        if (200 === $statusCode || 202 === $statusCode) {
            $content = $response->getBody()->getContents();

            $result = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            $this->logger->debug('FloodlightService get success with content sanitaze jhon throw '.json_last_error_msg());

            if (empty($result)) {
                $result = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
                $result = (array) $result;
                $this->logger->debug('FloodlightService get success with content sanitaze jhon throw2 '.json_last_error_msg());
            }
        }
        if (is_array($result)) {
            $result['statusRequestCode'] = $statusCode;
        }

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
        $url = sprintf('%s/%s/companies/ip/%s', self::FLOODLIGHT_API, self::FLOODLIGHT_VERSION, $ip);

        return $this->makeRequest($url);
    }

    public function getByDomain(string $domain): bool|array
    {

        $response   = $this->getCompanyByDomain($domain);
        // Check if don't have response

        if (false === $response) {
            return false;
        }
        return $this->handleResult($response);
    }

    private function getCompanyByDomain(string $ipHash): ResponseInterface|false
    {
        $url = sprintf('%s/%s/companies/domain/%s', self::FLOODLIGHT_API, self::FLOODLIGHT_VERSION, $ipHash);

        return $this->makeRequest($url);
    }

    /**
     * Prüft den Status der API-Autorisierung
     * 
     * @return array<mixed>
     */
    public function getAuthorizationStatus(): ResponseInterface
    {
        $url = sprintf('%s/%s/auth/usage', self::FLOODLIGHT_API, self::FLOODLIGHT_VERSION);
        $response = $this->makeRequest($url);
        return $response;
    }

    /**
     * Verarbeitet das Ergebnis der API-Key-Validierung und gibt eine entsprechende Meldung zurück
     */
    private function handleAuthorizationResult(ResponseInterface $result): JsonResponse
    {
        // Prüfe auf ungültigen API-Key (401 Unauthorized)
        $statusCode = $result->getStatusCode();
        if ($statusCode === 401) {
            return new JsonResponse(['message' => 'API key invalid'], Response::HTTP_UNAUTHORIZED);
        }

        $contents = $result->getBody()->getContents();
        $data = json_decode($contents, true);
        // Limit exceeded
        if ($statusCode === 200 && isset($data['remaining']) && $data['remaining'] === 0) {
            return new JsonResponse(['message' => 'API key valid, but quota exceeded for this period.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Remaining requests left
        if ($statusCode === 200 && isset($data['remaining']) && $data['remaining'] >= 0) {
            return new JsonResponse(['message' => 'API key valid, '.$data['remaining'].' API calls remaining for this period.'], Response::HTTP_OK);
        }

        //Non of the cases
        return new JsonResponse(['message' => 'Something went wrong with your validation: '.$statusCode], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Prüft den Status der API-Autorisierung und gibt eine lesbare Meldung zurück
     */
    public function validateApiKey(): JsonResponse
    {
        //if ($this->isAPIKeySet())
        $result = $this->getAuthorizationStatus();
        return $this->handleAuthorizationResult($result);
    }
}
