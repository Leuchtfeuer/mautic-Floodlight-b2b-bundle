<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\CoreBundle\Loader\ParameterLoader;
use Mautic\PageBundle\Entity\Hit;
use Mautic\PageBundle\Model\PageModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisPyService;
use Symfony\Component\HttpFoundation\Response;

class IpLookupRulesHelper
{
    public const LEAD_DOMAINS_STATUS_BLACKLISTED = 'blacklisted';
    public const LEAD_DOMAINS_STATUS_FAILURE     = 'failure';
    public const LEAD_DOMAINS_STATUS_VALID       = 'valid';

    private ParameterLoader $parameterLoader;

    public function __construct(
        private PageModel $pageModel,
        private FloodlightLeadDomainsModel $leadDomainsModel,
        private WhoisPyService $whoisPyService,
        private FloodlightCompanyDomainsModel $companyDomainsModel,
        private FloodlightService $floodlightService,
        private Config $config,
    ) {
        $this->parameterLoader = new ParameterLoader();
    }

    /**
     * @param array<string, mixed> $dataDomain
     *
     * @return array<string, mixed>|false
     */
    public function evaluate(Hit $hit, array $dataDomain): array|false
    {
        if (null === $hit->getLead()) {
            return false;
        }

        $resultLeadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($hit->getLead(), $dataDomain);

        $companyDomains   = $this->companyDomainsModel->getCompanyDomains($hit->getLead(), $dataDomain);

        if (empty($companyDomains)) {
            return false;
        }

        return [
            'domain' => $dataDomain['domain'],
            'result' => [
                'leadDomain'     => $resultLeadDomain,
                'companyDomains' => $companyDomains,
            ],
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

        if (!$this->checkIfWasRecent($hit->getLead()->getId())) {
            return false;
        }

        return true;
    }

    private function checkIfWasRecent(int $leadId): bool
    {
        $result = $this->leadDomainsModel->getRepository()->findOneBy(['contact' => $leadId], ['dateModified' => 'DESC']);

        if (empty($result)) {
            return true;
        }

        assert($result instanceof FloodlightLeadDomains);

        $hour = $this->parameterLoader->getParameterBag()->get('minimum_hour_to_new_entry', 2);
        if ($result->getDateModified() > (new \DateTime())->modify('-'.$hour.' hour')) {
            return false;
        }

        return true;
    }

    private function checkMoreThan2(int $leadId): bool
    {
        $result = $this->pageModel->getHitRepository()->findBy(['lead' => $leadId]);

        return count($result) > 1;
    }

    /**
     * @return array<string, mixed>|bool
     */
    private function getDataDomainByFloodlight(string $remoteAddr): array|bool
    {
        if (empty($remoteAddr)) {
            return false;
        }

        $data = $this->floodlightService->get($remoteAddr);

        $handleStatusRequest = [
            '404',
            '202',
            '200',
        ];

        if (
            false === $data
            || !is_array($data)
            || !in_array($data['statusRequestCode'], $handleStatusRequest)
        ) {
            return false;
        }

        $floodlightDTO = new FloodlightDTO($data);
        $domain        = $floodlightDTO->getCompanyPrimaryDomain();
        if (empty($domain) && !empty($floodlightDTO->getDomains())) {
            $domain = $floodlightDTO->getDomains()[0];
        }
        $status  = $this->getStatusDomain($domain);

        if (self::LEAD_DOMAINS_STATUS_BLACKLISTED === $status) {
            $domain = $status;
        } elseif (Response::HTTP_NOT_FOUND === $data['statusRequestCode']) {
            $domain          = self::LEAD_DOMAINS_STATUS_FAILURE;
            $status          = self::LEAD_DOMAINS_STATUS_FAILURE;
        } elseif (Response::HTTP_ACCEPTED === $data['statusRequestCode']) {
            $newFloodlightDTO = new FloodlightDTO([]);
            $newFloodlightDTO->setIpHash($floodlightDTO->getIpHash());
            $newFloodlightDTO->setDomains([]);
            $floodlightDTO = $newFloodlightDTO;
            $domain        = null;
        }

        return [
            'status'  => $status,
            'domain'  => $this->cleanDomain($domain),
            'details' => [
                'floodlight'    => $floodlightDTO,
                'statusRequest' => $data['statusRequestCode'],
            ],
        ];
    }

    private function cleanDomain(?string $domain): ?string
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
     * @return array<string, mixed>|false
     */
    public function getDomain(string $remoteAddr): array|false
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

    /**
     * @return array<string, mixed>
     */
    public function getDomainByGetHostByAddr(string $remoteAddr): array
    {
        $domain      = gethostbyaddr($remoteAddr);
        if (false === $domain) {
            $domain = null;
        }
        $status      = $this->getStatusDomain($domain);
        if (self::LEAD_DOMAINS_STATUS_BLACKLISTED === $status) {
            $domain = $status;
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
            'domain'  => $this->cleanDomain($domain),
            'details' => ['pyservice' => $details],
        ];
    }

    /**
     * Returns if the domain is blacklisted, valid or failed.
     *
     * @return self::LEAD_DOMAINS_STATUS_BLACKLISTED|self::LEAD_DOMAINS_STATUS_FAILURE|self::LEAD_DOMAINS_STATUS_VALID
     */
    private function getStatusDomain(?string $domain): string
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
