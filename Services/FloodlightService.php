<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use Psr\Http\Message\ResponseInterface;

class FloodlightService implements FloodlightServiceInterface
{
    private const TRY_COUNT = 3;

    private const FLOODLIGHT_API  = 'https://api-dev.floodlight-b2b.com';
    public const SECONDS_SLEEPING = 3;

    public function __construct(private ApiClient $client)
    {
    }

    public const FLOODLIGHT_VERSION = 'v1';

    /**
     * @return array<mixed>|bool
     */
    public function get(string $ip, int $try = 0): array|bool
    {
        $response   = $this->getCompanyByIp($ip);
        $statusCode = $response->getStatusCode();

        if (self::TRY_COUNT === $try + 1) {
            return false;
        }

        if (202 === $statusCode) {
            sleep(self::SECONDS_SLEEPING);

            return $this->get($ip, $try + 1);
        }

        if (200 !== $statusCode) {
            return false;
        }

        $result = json_decode($response->getBody()->getContents(), true);
        assert(is_array($result));

        return $result;
    }

    private function getCompanyByIp(string $ip): ResponseInterface
    {
        return $this->client->getClient()->request('GET', sprintf('%s/%s/companies/ip/%s', self::FLOODLIGHT_API, self::FLOODLIGHT_VERSION, $ip));
    }
}
