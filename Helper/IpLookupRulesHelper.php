<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\PageBundle\Entity\Hit;
use Mautic\PageBundle\Model\PageModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
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
        private FloodlightCompanyDomainsModel $companyDomainsModel,
        private FloodlightService $floodlightService,
        private Config $config
    ) {
    }

    /**
     * @param array{
     *     domain: string,
     *     status: string,
     *     details: array<string, mixed>
     * }
     *
     * @return array{
     *     domain: string,
     *     result: FloodlightLeadDomains,
     *     status: string
     * }
     */
    public function evaluate(Hit $hit, array $dataDomain): array
    {
        $result = $this->companyDomainsModel->updateOrCreateCompanyAndCompanyDomainAndAddOrCountLeadDomain($hit->getLead()->getId(), $dataDomain);

        return [
            'domain' => $dataDomain['domain'],
            'result' => $result,
            'status' => $dataDomain['status'],
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

    /*
     * @param string $remoteAddr
     * @return array{
     *   status: string,
     *  domain: string,
     *  details: array<string, FloodlightDTO>
    * }|bool
     */
    private function getDataDomainByFloodlight(string $remoteAddr): array|bool
    {
        if (empty($remoteAddr)) {
            return false;
        }
        $data = $this->floodlightService->get($remoteAddr);
        if (false === $data) {
            return false;
        }
        $floodlightDTO = new FloodlightDTO($data);

        $domain  = $floodlightDTO->getCompanyPrimaryDomain();

        $status = $this->getStatusDomain($domain);
        if (self::LEAD_DOMAINS_STATUS_BLACKLISTED === $status) {
            $domain = $status;
        }

        return [
            'status'  => $status,
            'domain'  => $domain,
            'details' => ['floodlight' => $floodlightDTO],
        ];
    }

    /*
     * @param string $remoteAddr
     * @return {
     *    status: string,
     *    domain: string,
     *    details: array<string, string|Floodlight>
     */
    public function getDomain(string $remoteAddr): array|bool
    {
        if ($this->config->isProTier()) {
            $floodlightData = $this->getDataDomainByFloodlight($remoteAddr);
            if (false !== $floodlightData) {
                return $floodlightData;
            }
        }

        $hostByAddrData  = $this->getDomainByGetHostByAddr($remoteAddr);
        if (self::LEAD_DOMAINS_STATUS_FAILURE !== $hostByAddrData['status']) {
            return $hostByAddrData;
        }

        $pyServiceData = $this->getDomainByWhoisPyService($remoteAddr);
        if (self::LEAD_DOMAINS_STATUS_FAILURE !== $pyServiceData['status']) {
            return $pyServiceData;
        }

        return false;
    }

    /*
     * @param string $remoteAddr
     * @return array{
     *  status: string,
     *  domain: string,
     *  details: array<string, string>
     * }
     */
    public function getDomainByGetHostByAddr(string $remoteAddr): array
    {
        $hostname    = gethostbyaddr($remoteAddr);
        $domainParts = explode('.', $hostname);
        $baseDomain  = array_slice($domainParts, -2, 2, true);
        $domain      = implode('.', $baseDomain);
        $status      = $this->getStatusDomain($domain);
        if (self::LEAD_DOMAINS_STATUS_BLACKLISTED === $status) {
            $domain = $status;
        }

        return [
            'status'  => $status,
            'domain'  => $domain,
            'details' => ['hostname' => $hostname],
        ];
    }

    /*
     * @param string $remoteAddr
     * @return array<string,string|array>
     */
    public function getDomainByWhoisPyService(string $remoteAddr): array
    {
        $domainWhois = $this->whoisPyService->getWhoisData($remoteAddr);
        $domain      = '';
        $details     = [];
        if (!empty($domainWhois) && isset($domainWhois['company_domain']) && is_string($domainWhois['company_domain'])) {
            $domain      = $domainWhois['company_domain'];
            $details     = $domainWhois;
        }

        $status = $this->getStatusDomain($domain);
        if (self::LEAD_DOMAINS_STATUS_BLACKLISTED === $status) {
            $domain = $status;
        }

        return [
            'status'  => $status,
            'domain'  => $domain,
            'details' => ['pyservice' => $details],
        ];
    }

    /**
     * Returns if the domain is blacklisted, valid or failed.
     *
     * @return self::LEAD_DOMAINS_STATUS_BLACKLISTED|self::LEAD_DOMAINS_STATUS_FAILURE|self::LEAD_DOMAINS_STATUS_VALID
     */
    private function getStatusDomain(string $domain): string
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
