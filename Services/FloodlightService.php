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
                        "ip": "192.168.1.1",
                        "primarydomain": "Leuchtfeuer.com",
                        "companyfloodlight_id": "b4ba2fa0b5dcc1215c8b38595b960e088799b590 ",
                        "bigpicture_id": "62079b01-19b5-4612-ba8f-e42a796d7637",
                        "companycrunchbase_handle": "organization/beacon-digital-marketing-eac1",
                        "companyname": "Leuchtfeuer",
                        "companyname_legal": "Leuchtfeuer Digital Marketing GmbH",
                        "companyname_alternatives": [
                            "Bitmotion",
                            "naw.info",
                            "Lighthouse Digital Marketing"
                        ],
                        "domainaliases": [
                            "bitmotion.de",
                            "Energy"
                        ],
                        "companywebsite": "https://Leuchtfeuer.com",
                        "companyemail": "hallo@Leuchtfeuer.com",
                        "companyindustry": "Electronics",
                        "companyindustry_linkedin": "Technology, Information and Media",
                        "companyindustry_list": [
                            "Electronics",
                            "Energy"
                        ],
                        "companylabels": [
                            "Finance",
                            "Insurance",
                            "B2C",
                            "B2B"
                        ],
                        "companyindustry_naicscode": "52",
                        "companyindustry_icb_industrygroup": "Personal-&-Household-Goods",
                        "companyindustry_icb_industry": "Personal-Goods",
                        "companyindustry_icb_subindustry": "footwear",
                        "companyindustry_icb_sector": "Consumer-Goods",
                        "companydescription_long": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr.",
                        "companydescription": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr,  sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet",
                        "companyfounding_year": "1923",
                        "name": "Goldman Sachs Group",
                        "companyaddress1": "Main street 1",
                        "companyaddress2": "Mansion House",
                        "companycity": "Hannover",
                        "companyzipcode": "30177",
                        "companystate": "New Jersey",
                        "companystate_code": "NJ",
                        "companycountry": "Germany",
                        "companycountry_code": "GE",
                        "companyrank_tranco": "28568",
                        "companynumber_of_employees": "13875",
                        "companyemployees_range": "20K+",
                        "companymarketcap": "365298532",
                        "companyannual_revenue": "123456789",
                        "companyannual_revenue_range": "$10B+",
                        "companyfunding_raised": "1000000",
                        "company_technologies": [
                            "Mautic",
                            "Typo3",
                            "open_search",
                            "react",
                            "php8",
                            "symfony"
                        ],
                        "companyfacebook_handle": "The Goldman Sachs Group, Inc.",
                        "companylinkedin_handle": "goldmansachs",
                        "companytwitter_handle": "goldmansachs",
                        "companytwitter_followers": "9243969",
                        "companytwitter_followers": "9243969",
                        "legalName": "The Goldman Sachs Group, Inc.",
                        "companylogo": "https://cdn.leuchtfeuer.com/typo3conf/ext/lf_site/Resources/Public/Images/Icons/logo_leuchtfeuer.svg",
                        "companytype": "Public",
                        "companyticker": "GS",
                        "companyphone": "+495116262930",
                        "companytimezone": "America/New_York",
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
