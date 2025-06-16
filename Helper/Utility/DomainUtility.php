<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisPyService;
use Pdp\Domain;
use Pdp\Rules;

class DomainUtility
{
    private ?Rules $rules = null;

    public function __construct(
        private FloodlightLeadDomainsModel $leadDomainsModel,
        private WhoisPyService $whoisPyService,
    ) {}

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

    /**
     * Bereinigt eine Domain mit der jeremykendall/php-domain-parser Bibliothek
     * und gibt nur den bereinigten Host zurück (registrable domain)
     */
    public function cleanDnsOrWhoisDomain(?string $domain): string
    {
        if (is_null($domain) || empty($domain)) {
            return 'Domain could not be cleaned';
        }

        try {
            // Initialisiere Rules nur einmal
            if ($this->rules === null) {
                $this->rules = Rules::fromPath(
                    'https://publicsuffix.org/list/public_suffix_list.dat'
                );
            }

            // Entferne Protokoll falls vorhanden
            $cleanInput = preg_replace('#^https?://#', '', $domain);
            
            // Entferne www. falls vorhanden
            $cleanInput = preg_replace('#^www\.#', '', $cleanInput);
            
            // Entferne Pfad, Query-Parameter etc.
            $cleanInput = parse_url('http://' . $cleanInput, PHP_URL_HOST) ?: $cleanInput;

            // Parse die Domain
            $domainObj = Domain::fromIDNA2008($cleanInput);
            $resolvedDomain = $this->rules->resolve($domainObj);
            
            // Gib nur den bereinigten Host zurück (registrable domain)
            $host = $resolvedDomain->registrableDomain()->toString();
            
            return !empty($host) ? $host : 'Domain could not be cleaned';
            
        } catch (\Exception $e) {
            return 'Domain could not be cleaned';
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
        $domain = gethostbyaddr($remoteAddr);
        $cleanDomain = $this->cleanDnsOrWhoisDomain($domain);

        if ($cleanDomain === 'Domain could not be cleaned') {
            $cleanDomain = null;
            $status = FloodlightLeadDomains::STATUS_NO_DATA_FOUND;
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
        $status = FloodlightLeadDomains::STATUS_NO_DATA_FOUND;

        if (!empty($domainWhois) && isset($domainWhois['company_domain']) && is_string($domainWhois['company_domain'])) {
            $domain = $domainWhois['company_domain'];
            $cleanDomain = $this->cleanDnsOrWhoisDomain($domain);
            if ($cleanDomain === 'Domain could not be cleaned') {
                $cleanDomain = null;
            } else {
                $domain = $cleanDomain;
                $details = $domainWhois;
                $status = $this->getStatusDomain($domain);
            }
        }

        return [
            'status'  => $status,
            'domain'  => $cleanDomain,
            'details' => ['pyservice' => $details],
        ];
    }

}