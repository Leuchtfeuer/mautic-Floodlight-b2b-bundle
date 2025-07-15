<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility;

use Mautic\CoreBundle\Helper\PathsHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\KeywordBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\PdpLoader;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisPyService;
use Pdp\Domain;
use Pdp\Rules;

class DomainUtility
{
    private ?Rules $rules = null;

    public function __construct(
        private WhoisPyService $whoisPyService,
        private KeywordBlacklistHelper $keywordBlacklistHelper,
        private DomainBlacklistHelper $domainBlacklistHelper,
        private PathsHelper $pathsHelper,
        private PdpLoader $pdpLoader,
    ) {
    }

    public function getStatusDomain(?string $domain): string
    {
        if (empty($domain)) {
            return FloodlightLeadDomains::STATUS_NO_DATA_FOUND;
        }

        if ($this->hasMoreThanFourNumbers($domain)) {
            return FloodlightLeadDomains::STATUS_IP_DOMAIN;
        }

        if ($this->isBlackListed($domain)) {
            return FloodlightLeadDomains::STATUS_BLACKLISTED;
        }

        return FloodlightLeadDomains::STATUS_SUCCESS;
    }

    /**
     * Clean domain with jeremykendall/php-domain-parser.
     */
    public function cleanDomain(?string $domain): ?string
    {
        $this->pdpLoader->load();

        if (is_null($domain) || empty($domain)) {
            return null;
        }

        try {
            if (null === $this->rules) {
                $suffixListPath = $this->pathsHelper->getPluginsPath().'/LeuchtfeuerFloodlightB2BBundle/Config/public_suffix_list.dat';
                $this->rules    = Rules::fromPath(
                    $suffixListPath
                );
            }

            $cleanInput = preg_replace('#^https?://#', '', $domain);
            if (null === $cleanInput) {
                return null;
            }

            $cleanInput = preg_replace('#^www\.#', '', $cleanInput);
            if (null === $cleanInput) {
                return null;
            }

            $cleanInput = parse_url('http://'.$cleanInput, PHP_URL_HOST) ?: $cleanInput;

            $domainObj      = Domain::fromIDNA2008($cleanInput);
            $resolvedDomain = $this->rules->resolve($domainObj);

            $host = $resolvedDomain->registrableDomain()->toString();

            return !empty($host) ? $host : null;
        } catch (\Exception) {
            return null;
        }
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
        $domain       = gethostbyaddr($remoteAddr);
        $domainString = false !== $domain ? $domain : null;
        $cleanDomain  = $this->cleanDomain($domainString);

        if (null === $cleanDomain) {
            $status      = FloodlightLeadDomains::STATUS_NO_DATA_FOUND;
        } else {
            $status = $this->getStatusDomain($cleanDomain);
        }

        return [
            'status'  => $status,
            'domain'  => $cleanDomain,
            'details' => ['hostname' => $domain],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function getDomainByWhoisPyService(string $remoteAddr): array
    {
        $domainWhois = $this->whoisPyService->getWhoisData($remoteAddr);
        $cleanDomain = '';
        $details     = [];
        $status      = FloodlightLeadDomains::STATUS_NO_DATA_FOUND;

        if (!empty($domainWhois) && isset($domainWhois['company_domain']) && is_string($domainWhois['company_domain'])) {
            $domain      = $domainWhois['company_domain'];
            $cleanDomain = $this->cleanDomain($domain);
            if (!is_null($cleanDomain)) {
                $domain  = $cleanDomain;
                $details = $domainWhois;
                $status  = $this->getStatusDomain($domain);
            }
        }

        return [
            'status'  => $status,
            'domain'  => $cleanDomain,
            'details' => ['pyservice' => $details],
        ];
    }

    public function isBlackListed(string $domain): bool
    {
        $keywordBlacklist = $this->keywordBlacklistHelper->get();
        $domainLower      = strtolower($domain);

        foreach ($keywordBlacklist as $keyword) {
            if (str_contains($domainLower, strtolower($keyword))) {
                return true;
            }
        }

        $blacklist = $this->domainBlacklistHelper->get();

        if (!empty($blacklist) && in_array($domain, $blacklist, true)) {
            return true;
        }
        foreach ($blacklist as $blacklistedDomain) {
            if (fnmatch('*.'.$blacklistedDomain, $domain)) {
                return true;
            }
        }

        return false;
    }
}
