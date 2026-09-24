<?php

declare(strict_types=1);

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

    /**
     * @var string[]
     */
    private const ALLOWED_LOGO_HOSTS = [
        'api.floodlight-b2b.com',
        'api-dev.floodlight-b2b.com',
    ];

    public function __construct(private ApiClient $client, private LoggerInterface $logger, private string $floodlightApiUrl = 'https://api.floodlight-b2b.com')
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
                return $e->getResponse();
            }
        }

        return false;
    }

    /**
     * @return array{content: string, contentType: string}|null
     */
    public function getLogo(string $logoUrl): ?array
    {
        if (!$this->isAllowedLogoOrigin($logoUrl)) {
            return null;
        }

        try {
            $response = $this->client->getClient()->request('GET', $logoUrl);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            return [
                'content'     => $response->getBody()->getContents(),
                'contentType' => $response->getHeaderLine('Content-Type'),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('FloodlightService getLogo failed: '.$e->getMessage());

            return null;
        }
    }

    public function isAllowedLogoOrigin(string $url): bool
    {
        $target = parse_url($url);

        if (!is_array($target) || 'https' !== ($target['scheme'] ?? '') || !isset($target['host'])) {
            $this->logger->warning('Floodlight: skipped company logo from unexpected origin.', ['url' => $url]);

            return false;
        }

        $configuredHost = parse_url($this->floodlightApiUrl, PHP_URL_HOST);
        $allowedHosts   = self::ALLOWED_LOGO_HOSTS;

        if (is_string($configuredHost) && '' !== $configuredHost) {
            $allowedHosts[] = $configuredHost;
        }

        foreach ($allowedHosts as $allowedHost) {
            if (0 === strcasecmp($target['host'], $allowedHost)) {
                return true;
            }
        }

        $this->logger->warning('Floodlight: skipped company logo from unexpected origin.', ['url' => $url]);

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

    public function getAuthorizationStatus(): ResponseInterface|false
    {
        $url = sprintf('%s/%s/auth/usage', $this->floodlightApiUrl, self::FLOODLIGHT_VERSION);

        return $this->makeRequest($url);
    }

    private function handleAuthorizationResult(ResponseInterface $result): JsonResponse
    {
        $statusCode = $result->getStatusCode();

        if (200 === $statusCode) {
            // Status 200 means the credentials are valid, whatever the body contains
            return new JsonResponse(['message' => 'Valid credentials'], Response::HTTP_OK);
        }

        // Any other status means the credentials are invalid
        return new JsonResponse(['message' => 'Your credentials are invalid'], Response::HTTP_UNAUTHORIZED);
    }

    public function validateApiKey(): JsonResponse
    {
        $result = $this->getAuthorizationStatus();

        if (false === $result) {
            return new JsonResponse(['message' => 'Could not connect to API for validation.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->handleAuthorizationResult($result);
    }

    /**
     * Validates API credentials directly and returns only true/false.
     * The Symfony validator uses this for form data (BEFORE the data is saved).
     */
    public function validateApiCredentials(string $accessKeyId, string $secretKey): bool
    {
        try {
            // Build the credentials locally because the form data is not stored yet
            $validationCredentials = new ValidationCredentials();
            $validationCredentials->setApiAccessKeyId($accessKeyId);
            $validationCredentials->setApiSecretKey($secretKey);

            $this->client->setCredentials($validationCredentials);

            $jsonResponse = $this->validateApiKey();

            return 200 === $jsonResponse->getStatusCode() || 429 === $jsonResponse->getStatusCode();
        } catch (\Exception $e) {
            $this->logger->error('FloodlightService validateApiCredentials failed: '.$e->getMessage());

            return false;
        }
    }
}
