<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility;

use Mautic\CoreBundle\Helper\PathsHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\KeywordBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisService;
use Pdp\Domain;
use Pdp\Rules;
use Psr\Log\LoggerInterface;

class DomainUtility
{
    private ?Rules $rules = null;

    public function __construct(
        private WhoisService $whoisService,
        private KeywordBlacklistHelper $keywordBlacklistHelper,
        private DomainBlacklistHelper $domainBlacklistHelper,
        private PathsHelper $pathsHelper,
        protected LoggerInterface $mauticLogger,
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
        if (is_null($domain) || empty($domain)) {
            return null;
        }

        if (!class_exists(Rules::class)) {
            $this->mauticLogger->error('jeremykendall/php-domain-parser is not installed. Run "composer require jeremykendall/php-domain-parser:^6.3".');

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

    protected function lookupHostname(string $remoteAddr): string|false
    {
        return gethostbyaddr($remoteAddr);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDomainByGetHostByAddr(string $remoteAddr): array
    {
        try {
            $domain       = $this->lookupHostname($remoteAddr);
        } catch (\Exception) {
            $domain = false;
        }
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
    public function getDomainByWhoisService(string $remoteAddr): array
    {
        $domainWhois = $this->whoisService->getWhoisData($remoteAddr);
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
        $domain             = strtolower($domain);
        $inKeywordBlacklist = $this->checkIfInKeywordBlacklist($domain);
        $inDomainBlacklist  = $this->checkIfInDomainBlacklist($domain);
        if (true === $inKeywordBlacklist || true === $inDomainBlacklist) {
            return true;
        }

        return false;
    }

    private function checkIfInKeywordBlacklist(string $domain): bool
    {
        $this->keywordBlacklistHelper->setBlacklist();
        if (false === $this->keywordBlacklistHelper->checkIfBlacklistLoaded()) {
            $this->mauticLogger->warning('Keyword blacklist file not found.');

            return false;
        }

        $keywordBlacklist = $this->keywordBlacklistHelper->get();
        foreach ($keywordBlacklist as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) {
                continue;
            }
            if (str_contains($domain, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function checkIfInDomainBlacklist(string $domain): bool
    {
        $this->domainBlacklistHelper->setBlacklist();
        if (false === $this->domainBlacklistHelper->checkIfBlacklistLoaded()) {
            $this->mauticLogger->warning('Domain blacklist file not found.');

            return false;
        }

        $blacklist = $this->domainBlacklistHelper->get();
        foreach ($blacklist as $blacklistedDomain) {
            $blacklistedDomain = trim($blacklistedDomain);
            if (empty($blacklistedDomain)) {
                continue;
            }

            if ($domain === strtolower($blacklistedDomain)) {
                return true;
            }

            if (fnmatch('*.'.strtolower($blacklistedDomain), $domain)) {
                return true;
            }
        }

        return false;
    }
}
