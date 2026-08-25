<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard;

use GuzzleHttp\Exception\BadResponseException;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Requests the Floodlight usage endpoint, validates the response and returns
 * a UsageData value object. Controlled failures are exposed as UsageException
 * categories so the dashboard never consumes raw PSR-7 responses or unvalidated
 * arrays.
 */
class UsageProvider
{
    private const USAGE_PATH = '/v1/auth/usage';

    private const CONNECT_TIMEOUT = 2;

    private const REQUEST_TIMEOUT = 5;

    public function __construct(
        private ApiClient $apiClient,
        private Config $config,
        private LoggerInterface $logger,
        private string $floodlightApiUrl,
    ) {
    }

    /**
     * @throws UsageException
     */
    public function getUsage(): UsageData
    {
        if (!$this->config->isPublished() || !$this->config->isConfigured()) {
            throw UsageException::notConfigured();
        }

        $response = $this->request();

        return $this->mapResponse($response);
    }

    private function request(): ResponseInterface
    {
        $url = rtrim($this->floodlightApiUrl, '/').self::USAGE_PATH;

        try {
            return $this->apiClient->getClient()->request('GET', $url, [
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout'         => self::REQUEST_TIMEOUT,
            ]);
        } catch (BadResponseException $e) {
            // 4xx/5xx responses are available and mapped to a category.
            throw $this->mapStatusCode($e->getResponse()->getStatusCode());
        } catch (\Throwable $e) {
            // Transport errors and any other unexpected failure must not fail
            // the widget request. Log without credentials or headers.
            $this->logger->error('Floodlight usage request failed: '.$e->getMessage());

            throw UsageException::unavailable('Transport error while requesting Floodlight usage.');
        }
    }

    private function mapResponse(ResponseInterface $response): UsageData
    {
        $statusCode = $response->getStatusCode();

        if (200 !== $statusCode) {
            throw $this->mapStatusCode($statusCode);
        }

        try {
            $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Floodlight usage response contained malformed JSON: '.$e->getMessage());

            throw UsageException::unavailable('Malformed Floodlight usage response.');
        }

        if (!is_array($decoded)) {
            $this->logger->error('Floodlight usage response was not a JSON object.');

            throw UsageException::unavailable('Unexpected Floodlight usage response.');
        }

        return $this->buildUsageData($decoded);
    }

    /**
     * @param array<mixed> $data
     */
    private function buildUsageData(array $data): UsageData
    {
        $remaining    = $this->readNonNegativeInt($data, 'remaining');
        $monthlyLimit = $this->readPositiveInt($data, 'monthlyLimit');
        $buffer       = $this->readNonNegativeInt($data, 'buffer');
        $nextRenewal  = $this->readDate($data, 'nextRenewal');

        return new UsageData($remaining, $monthlyLimit, $buffer, $nextRenewal);
    }

    /**
     * @param array<mixed> $data
     */
    private function readNonNegativeInt(array $data, string $key): int
    {
        $value = $this->readInt($data, $key);

        if ($value < 0) {
            throw $this->invalidField($key);
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function readPositiveInt(array $data, string $key): int
    {
        $value = $this->readInt($data, $key);

        if ($value <= 0) {
            throw $this->invalidField($key);
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function readInt(array $data, string $key): int
    {
        if (!array_key_exists($key, $data) || !is_int($data[$key])) {
            throw $this->invalidField($key);
        }

        return $data[$key];
    }

    /**
     * @param array<mixed> $data
     */
    private function readDate(array $data, string $key): \DateTimeImmutable
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key]) || '' === $data[$key]) {
            throw $this->invalidField($key);
        }

        try {
            return new \DateTimeImmutable($data[$key]);
        } catch (\Exception $e) {
            $this->logger->error('Floodlight usage response contained an invalid renewal date: '.$e->getMessage());

            throw UsageException::unavailable('Invalid Floodlight renewal date.');
        }
    }

    private function invalidField(string $key): UsageException
    {
        $this->logger->error(sprintf('Floodlight usage response contained an invalid "%s" field.', $key));

        return UsageException::unavailable(sprintf('Invalid Floodlight usage field: %s.', $key));
    }

    private function mapStatusCode(int $statusCode): UsageException
    {
        return match ($statusCode) {
            401, 403 => UsageException::invalidCredentials(),
            429      => UsageException::rateLimited(),
            default  => $this->unavailableStatus($statusCode),
        };
    }

    private function unavailableStatus(int $statusCode): UsageException
    {
        $this->logger->error(sprintf('Floodlight usage request returned unexpected status %d.', $statusCode));

        return UsageException::unavailable(sprintf('Unexpected Floodlight usage status %d.', $statusCode));
    }
}
