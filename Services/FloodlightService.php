<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use Psr\Http\Message\ResponseInterface;

class FloodlightService implements FloodlightServiceInterface
{
    public const FLOODLIGHT_API = 'https://api-dev.floodlight-b2b.com';

    public function __construct(private ApiClient $client)
    {
    }

    public const FLOODLIGHT_VERSION = 'v1';

    public function get(string $ip): array|bool
    {
        $response = $this->getCompanyByIp($ip);

        if (200 !== $response->getStatusCode()) {
            return false;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    private function getCompanyByIp(string $ip): ResponseInterface
    {
        return $this->client->getClient()->request('GET', sprintf('%s/%s/companies/ip/%s', self::FLOODLIGHT_API, self::FLOODLIGHT_VERSION, $ip));
    }
}
