<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use GuzzleHttp\Exception\ClientException;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class FloodlightService implements FloodlightServiceInterface
{
    private const FLOODLIGHT_API  = 'https://api.floodlight-b2b.com';

    public function __construct(
        private ApiClient $client,
        private LoggerInterface $logger
    ) {
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

    public function getByIpHash(string $ipHash): bool|array
    {
        $response                      = json_decode(file_get_contents(__DIR__.'/../Tests/fixtures/floodlight.json'), true);
        $response['statusRequestCode'] = 200;

        return $response;

        //        $response   = $this->getCompanyByIpHash($ipHash);
        //
        //        // Check if don't have response
        //        if (false === $response) {
        //            return false;
        //        }
        //
        //        return $this->handleResult($response);
    }

    public function getByDomain(string $domain): bool|array
    {
        // fake response
        $response                      = json_decode(file_get_contents(__DIR__.'/../Tests/fixtures/floodlight.json'), true);
        $response['statusRequestCode'] = 200;

        return $response;

        //        $response   = $this->getCompanyByDomain($domain);
        //
        //        // Check if don't have response
        //        if (false === $response) {
        //            return false;
        //        }
        //
        //        return $this->handleResult($response);
    }
}
