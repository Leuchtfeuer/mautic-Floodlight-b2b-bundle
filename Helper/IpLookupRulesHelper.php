<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\PageBundle\Entity\Hit;
use Mautic\PageBundle\Model\PageModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisPyService;

class IpLookupRulesHelper
{
    public const LEAD_DOMAINS_STATUS_BLACKLISTED = 'blacklisted';
    public const LEAD_DOMAINS_STATUS_FAILURE     = 'failure';
    public const LEAD_DOMAINS_STATUS_VALID       = 'valid';

    public function __construct(
        private PageModel $pageModel,
        private FloodlightLeadDomainsModel $leadDomainsModel,
        private WhoisPyService $whoisPyService,
        private FloodlightCompanyDomainsModel $companyDomainsModel
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluate(Hit $hit, string $domain, array $domainDetails=[]): array
    {
        $result = $this->companyDomainsModel->updateOrCreateCompanyAndCompanyDomainAndAddOrCountLeadDomain($hit->getLead()->getId(), $domain, $domainDetails);

        return [
            'domain' => $domain,
            'result' => $result,
        ];
    }

    public function isIp(string $domain): bool
    {
        $ip = str_replace('.', '', $domain);
        if (is_numeric($ip)) {
            return true;
        }

        return false;
    }

    public function check(Hit $hit): bool
    {
        if (!$this->checkMoreThan2($hit->getLead()->getId())) {
            return false;
        }

        if (!$this->lookupHasMoreThanOneHour($hit->getLead()->getId())) {
            return false;
        }

        return true;
    }

    private function lookupHasMoreThanOneHour(int $leadId): bool
    {
        $result = $this->leadDomainsModel->getRepository()->findOneBy(['lead' => $leadId], ['dateModified' => 'DESC']);

        if (empty($result)) {
            return true;
        }

        if ($result->getDateModified() > (new \DateTime())->modify('-1 hour')) {
            return false;
        }

        return true;
    }

    private function checkMoreThan2($leadId): bool
    {
        $result = $this->pageModel->getHitRepository()->findBy(['lead' => $leadId]);

        return count($result) > 1;
    }

    public function getDomain(string $remoteAddr): array
    {
        $type    = 'getHostByAddr';
        $details = [];
        $domain  = $this->getDomainByGetHostByAddr($remoteAddr);
        if (self::LEAD_DOMAINS_STATUS_FAILURE === $domain) {
            $type          = 'whoisPyService';
            $domainByWhois = $this->getDomainByWhoisPyService($remoteAddr);
            $domain        = $domainByWhois['domain'];
            $details       = $domainByWhois['details'];
        }

        if (self::LEAD_DOMAINS_STATUS_FAILURE === $domain) {
            $type = '';
        }

        return [
            'domain'  => $domain,
            'type'    => $type,
            'details' => $details,
        ];
    }

    public function getDomainByGetHostByAddr(string $remoteAddr): string
    {
        $hostname    = gethostbyaddr($remoteAddr);
        $domainParts = explode('.', $hostname);
        $baseDomain  = array_slice($domainParts, -2, 2, true);
        $domain      = implode('.', $baseDomain);
        $status      = $this->getStatus($domain);
        if (self::LEAD_DOMAINS_STATUS_VALID !== $status) {
            return $status;
        }

        return $domain;
    }

    public function getDomainByWhoisPyService(string $remoteAddr): array
    {
        $domainWhois = $this->whoisPyService->getWhoisData($remoteAddr);
        $domain      = '';
        $details     = [];
        if (!empty($domainWhois) && isset($domainWhois['company_domain']) && is_string($domainWhois['company_domain'])) {
            $domain      = $domainWhois['company_domain'];
            $details     = $domainWhois;
        }
        $status = $this->getStatus($domain);
        if (self::LEAD_DOMAINS_STATUS_VALID !== $status) {
            $domain = $status;
        }

        return [
            'domain'  => $domain,
            'details' => $details,
        ];
    }

    private function getStatus(string $domain): string
    {
        if (empty($domain) || $this->isIp($domain)) {
            return self::LEAD_DOMAINS_STATUS_FAILURE;
        }

        if ($this->leadDomainsModel->isBlackListed($domain)) {
            return self::LEAD_DOMAINS_STATUS_BLACKLISTED;
        }

        return self::LEAD_DOMAINS_STATUS_VALID;
    }
}
