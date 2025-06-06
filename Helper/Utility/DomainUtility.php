<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisPyService;

class DomainUtility
{
    public function __construct(
        private FloodlightLeadDomainsModel $leadDomainsModel,
                                private WhoisPyService $whoisPyService,){}

    public function getStatusDomain(?string $domain): string
    {
        if (empty($domain)) {
            return FloodlightLeadDomains::STATUS_NO_DATA_FOUND;
        }

        if ($this->hasMoreThanFourNumbers($domain)) {
            return FloodlightLeadDomains::STATUS_IP_DOMAIN;
        }

        if ($this->leadDomainsModel->isBlackListed($domain)) {
            return FloodlightLeadDomains::STATUS_BLACKLISTED;
        }

        return FloodlightLeadDomains::STATUS_SUCCESS;
    }

    public function cleanDomain(?string $domain): ?string
    {
        if (is_null($domain)) {
            return null;
        }
        $parts = explode('.', $domain);
        reset($parts);
        $firstPart = current($parts);
        preg_match_all('/\d/', $firstPart, $matches);
        reset($parts);
        if (count($matches[0]) > 1) {
            return implode('.', array_slice($parts, 1));
        }

        return $domain;
    }

    public function hasMoreThanFourNumbers(string $domain): bool
    {
        if (preg_match_all('/\d/', $domain) > 4) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDomainByGetHostByAddr(string $remoteAddr): array
    {
        $domain = gethostbyaddr($remoteAddr);

        if (false === $domain) {
            $domain = null;
            $status = FloodlightLeadDomains::STATUS_NO_DATA_FOUND;
        } else {
            $status = $this->getStatusDomain($domain);
        }

        return [
            'status'  => $status,
            'domain'  => $this->cleanDomain($domain),
            'details' => ['hostname' => $domain],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function getDomainByWhoisPyService(string $remoteAddr): array
    {
        $domainWhois = $this->whoisPyService->getWhoisData($remoteAddr);
        $domain      = '';
        $details     = [];
        $status = FloodlightLeadDomains::STATUS_NO_DATA_FOUND;

        if (!empty($domainWhois) && isset($domainWhois['company_domain']) && is_string($domainWhois['company_domain'])) {
            $domain = $domainWhois['company_domain'];
            $details = $domainWhois;
            $status = $this->getStatusDomain($domain);
        }

        return [
            'status'  => $status,
            'domain'  => $this->cleanDomain($domain),
            'details' => ['pyservice' => $details],
        ];
    }

}