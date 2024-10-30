<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class FloodlightService implements FloodlightServiceInterface
{
    private const TRY_COUNT = 3;

    private const FLOODLIGHT_API  = 'https://api-dev.floodlight-b2b.com';
    public const SECONDS_SLEEPING = 3;

    public function __construct(
        private ApiClient $client,
        private LoggerInterface $logger
    )
    {
    }

    public const FLOODLIGHT_VERSION = 'v1';

    /**
     * @return array<mixed>|bool
     */
    public function get(string $ip, int $try = 0): array|bool
    {
        // Check if had more than 3 tries of request 202
        if (self::TRY_COUNT === $try) {
            $this->logger->error('FloodlightService get fail, status 202 for 3 times with ip : '.$ip);
            return false;
        }

        $response   = $this->getCompanyByIp($ip);
        // Check if response is Status Code with Error like 404, 500, etc.
        if (false === $response) {
            return false;
        }

        $statusCode = $response->getStatusCode();

        if (200 === $statusCode) {
            $this->logger->info('FloodlightService get success with ip : '.$ip);
            $this->logger->info('FloodlightService get success with content : '.$response->getBody()->getContents());
            $result = json_decode($response->getBody()->getContents(), true);
            assert(is_array($result));
            $this->logger->info('FloodlightService get success with content sanitaze: '.json_encode($result));
            return $result;
        }

        if (202 === $statusCode) {
            sleep(self::SECONDS_SLEEPING);

            return $this->get($ip, $try + 1);
        }
        $this->logger->error('FloodlightService get fail, status '.$response->getStatusCode().' with ip : '.$ip);
        // Check if response is Status Code success but different of 200
        return false;
    }

    private function getCompanyByIp(string $ip): ResponseInterface|bool
    {
        $url = sprintf('%s/%s/companies/ip/%s', self::FLOODLIGHT_API, self::FLOODLIGHT_VERSION, $ip);

        try {
            return $this->client->getClient()->request('GET',$url );
        } catch (\Exception $e) {

            $this->logger->error('Url client get : '.$url);
            $this->logger->error('header '.$this->client->getClient()->getConfig());
            $this->logger->error('FloodlightService getCompanyByIp fail: '.$e->getMessage());
            return false;
        }
    }
}
