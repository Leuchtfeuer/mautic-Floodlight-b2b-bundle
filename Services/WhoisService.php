<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use Psr\Log\LoggerInterface;

class WhoisService
{
    private const RDAP_URL = 'https://rdap.arin.net/registry/ip/';

    private const ISP_KEYWORDS = ['isp', 'internet service provider', 'tele', 'telecom', 'telecommunication', 'telnet', 'mobile', 'wireless'];

    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * @return array<mixed>
     */
    public function getWhoisData(string $ip): array
    {
        if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return [];
        }

        $data = $this->fetchRdap($ip);
        if (null === $data) {
            return [];
        }

        $companyName   = $this->extractName($data);
        $companyDomain = $this->extractDomain($data);

        return [
            'company_domain' => $companyDomain,
            'company_name'   => $companyName,
            'is_isp'         => $this->isIsp($companyName, $companyDomain),
            'is_dialup'      => false,
        ];
    }

    /**
     * @return array<mixed>|null
     */
    private function fetchRdap(string $ip): ?array
    {
        $ch = curl_init(self::RDAP_URL.rawurlencode($ip));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Accept: application/rdap+json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (false === $response || 200 !== $httpCode) {
            $this->logger->error('WhoisService RDAP request failed for IP: '.$ip);

            return null;
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            $this->logger->error('WhoisService RDAP invalid JSON for IP: '.$ip);

            return null;
        }

        return $data;
    }

    /**
     * @param array<mixed> $data
     */
    private function extractName(array $data): string
    {
        foreach ($data['entities'] ?? [] as $entity) {
            foreach ($entity['vcardArray'][1] ?? [] as $vcard) {
                if (isset($vcard[0], $vcard[3]) && 'fn' === $vcard[0]) {
                    return (string) $vcard[3];
                }
            }
        }

        return (string) ($data['name'] ?? '');
    }

    /**
     * @param array<mixed> $data
     */
    private function extractDomain(array $data): string
    {
        foreach ($data['entities'] ?? [] as $entity) {
            foreach ($entity['vcardArray'][1] ?? [] as $vcard) {
                if (isset($vcard[0], $vcard[3]) && 'email' === $vcard[0]) {
                    $parts = explode('@', (string) $vcard[3]);
                    if (2 === count($parts)) {
                        return $parts[1];
                    }
                }
            }
        }

        return '';
    }

    private function isIsp(string $name, string $domain): bool
    {
        $name   = strtolower($name);
        $domain = strtolower($domain);

        foreach (self::ISP_KEYWORDS as $keyword) {
            if (str_contains($name, $keyword) || str_contains($domain, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
