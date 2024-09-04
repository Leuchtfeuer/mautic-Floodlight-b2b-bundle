<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FloodlightService implements FloodlightServiceInterface
{
    public const API_URL = 'https://floodlight-b2b.com/v1/companies/domain=';

    public function __construct(private Config $config, private HttpClientInterface $client)
    {
    }

    public function get(string $ip): bool|array
    {
        if (empty($ip)) {
            return false;
        }
        $apiKey        = $this->config->getApiKeys()['api_key'];
        $resultRequest = $this->client->request(
            'GET',
            self::API_URL.$ip,
            [
                'auth_bearer' => $apiKey,
            ]
        );

        if (Response::HTTP_OK !== $resultRequest->getStatusCode()) {
            return [
                'error'   => Response::HTTP_BAD_REQUEST == $resultRequest->getStatusCode() ? 'Bad request' : 'Error',
                'status'  => $resultRequest->getStatusCode(),
                'content' => [],
            ];
        }

        return [
            'error'   => null,
//            'status'  => $resultRequest->getStatusCode(),
            'status'  => 200,
//            'content' => !empty($resultRequest->getContent()) ? $resultRequest->toArray() : [],
            'content' => $this->testJson(),
        ];
    }

    private function testJson(): array
    {
        $json = '{
                    "domain": "http://abc.de ",
                    "company": {
                        "id": "60b725f10c9c85c70d97880dfe8191b3",
                        "primary_domain": "http://goldmansachs.com ",
                        "name": "Goldman Sachs Group",
                        "address1": "Main street 1",
                        "address2": "Mansion House",
                        "address2": "Mansion House",
                        "city": "Hannover",
                        "zip": "30177",
                        "country": "Germany",
                        "legalName": "The Goldman Sachs Group, Inc.",
                        "logo": "http://<hostname>/logo/goldmansachs.com",
                        "url": "https://www.goldmansachs.com/ ",
                        "wzl": "23457",
                        "naics": "abc",
                        "isic": "123",
                        "nace": "xyz",
                        "uk-sic": "987654",
                        "industry tags": [
                            "Finance",
                            "Financial Services",
                            "FinTech"
                        ]
                    }
                }';

        return json_decode($json, true);
    }
}
