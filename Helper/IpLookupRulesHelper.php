<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\CoreBundle\Loader\ParameterLoader;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PageBundle\Entity\Hit;
use Mautic\PageBundle\Model\PageModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status200Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisPyService;
use Symfony\Component\HttpFoundation\Response;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Reporter;

class IpLookupRulesHelper
{

    private ParameterLoader $parameterLoader;


    public function __construct(
        private PageModel $pageModel,
        private FloodlightLeadDomainsModel $leadDomainsModel,
        private WhoisPyService $whoisPyService,
        private FloodlightCompanyDomainsModel $companyDomainsModel,
        private FloodlightService $floodlightService,
        private Config $config,
        private FieldHelper $fieldHelper,
        private CompanyModel $companyModel,
        private FloodlightCompanyDomainsModel $floodlightCompanyDomainsModel,
        private LeadModel $leadModel,
        private Reporter $reporter,
        private Status200Strategy $status200Strategy
    ) {
        $this->parameterLoader = new ParameterLoader();
    }

    /**
     * @param array<string, mixed> $dataDomain
     *
     * @return array<string, mixed>|false
     */
    public function evaluate(Lead $lead, array $dataDomain): array|false
    {

        $resultLeadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $dataDomain);

        $companyDomains   = $this->companyDomainsModel->getCompanyDomains($lead, $dataDomain);

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
    private function getDataDomainByFloodlight(array $data): array|bool
    {


        $floodlightDTO = new FloodlightDTO($data);
        $domain        = $floodlightDTO->getCompanyPrimaryDomain();
        if (empty($domain) && !empty($floodlightDTO->getDomains())) {
            $domain = $floodlightDTO->getDomains()[0];
        }
        
        // Bestimme zuerst den allgemeinen Status basierend auf der Domain
        $status = $this->getStatusDomain($domain);

        // HTTP-Status überschreibt den Domain-Status bei Spezialfällen
        if (Response::HTTP_NOT_FOUND === $data['statusRequestCode']) {
            $status = FloodlightLeadDomains::STATUS_NO_DATA_FOUND;
            $domain = null;
            $testIpHash = $floodlightDTO->getIpHash();
        } elseif (Response::HTTP_ACCEPTED === $data['statusRequestCode']) {
            $status = FloodlightLeadDomains::STATUS_RETRY;
            $newFloodlightDTO = new FloodlightDTO([]);
            $newFloodlightDTO->setIpHash($floodlightDTO->getIpHash());
            $newFloodlightDTO->setDomains([]);
            $floodlightDTO = $newFloodlightDTO;
            $domain = null;
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

    private function checkIfInListOfValidRequestStatuses($data): bool
    {
        $validStatusRequest = [
            '404',
            '202',
            '200',
            '429',
            '401',
        ];

        if (
            false === $data
            || !is_array($data)
            || !in_array($data['statusRequestCode'], $validStatusRequest)
        ) {
            return false;
        }
        return true;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getDomain(string $remoteAddr, $lead): array|false
    {

        $data = $this->floodlightService->get($remoteAddr);


        if (!$this->checkIfInListOfValidRequestStatuses($data)) {
            return false;
        }

        if($data['statusRequestCode'] == 200){
            $processData = $this->status200Strategy->prepareData($data);

            $this->status200Strategy->processData($processData, $lead);

            return false;

        }

       //handleStatuses

        // handle 200 202 404
        $floodlightData = $this->getDataDomainByFloodlight($data);
        if (false !== $floodlightData) {
            return $floodlightData;
        }


        //Handle status 429

        $hostByAddrData = $this->getDomainByGetHostByAddr($remoteAddr);
        if (FloodlightLeadDomains::STATUS_NO_DATA_FOUND !== $hostByAddrData['status']
            //&& self::LEAD_DOMAINS_STATUS_FAILURE !== $hostByAddrData['status']
        ) {
            return $hostByAddrData;
        }

        $pyServiceData = $this->getDomainByWhoisPyService($remoteAddr);
        if (FloodlightLeadDomains::STATUS_NO_DATA_FOUND !== $pyServiceData['status']
            //&& self::LEAD_DOMAINS_STATUS_FAILURE !== $pyServiceData['status']
        ) {
            return $pyServiceData;
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

    /**
     * Returns if the domain is blacklisted, valid or failed.
     *
     * @return string
     */
    public function getStatusDomain(?string $domain): string
    {
        if (empty($domain)) {
            return FloodlightLeadDomains::STATUS_NO_DATA_FOUND;
        }
        
        if ($this->isIp($domain)) {
            return FloodlightLeadDomains::STATUS_IP_DOMAIN;
        }

        if ($this->leadDomainsModel->isBlackListed($domain)) {
            return FloodlightLeadDomains::STATUS_BLACKLISTED;
        }

        return FloodlightLeadDomains::STATUS_SUCCESS;
    }


    public function notifyNoDataFromGetDomain()
    {
            $this->reporter->notify('Domain for given IP not found');
    }

    /**
     *
     * @param array<string, mixed> $dataDomain
     *
     */
    public function processFloodlightLeadDomains(Lead $lead, array $dataDomain) {



        $result = $this->evaluate($lead, $dataDomain);

        if (
            false === $result
            || FloodlightLeadDomains::STATUS_BLACKLISTED === $result['status']
            || FloodlightLeadDomains::STATUS_NO_DATA_FOUND === $result['status']
        ) {
            if (false !== $result) {
                $this->reporter->notify('Domain is blacklisted or failed to evaluate: Domain. ' . $result['domain'] . ' status ' . $result['status']);

                return;
            }
            $this->reporter->notify('Domain not found');

            return;
        }

        if (!is_array($result['result']) || !is_array($result['result']['companyDomains'])) {
            $this->reporter->notify('Evaluation failed');

            return;
        }

        $companyDomains = $result['result']['companyDomains']['data'];

        // TODO: Implement updateCompanyCompanyDomains test
        if (!$result['result']['companyDomains']['isNew']) {
            $floodlightCompanyDomain = $this->updateCompanyCompanyDomains($dataDomain, $companyDomains, $lead);
            $companyDomains = $floodlightCompanyDomain;
        }
        $this->leadDomainsModel->recalculateLeadPrimaryCompany($lead);
    }
//copied from floodlight subscriber
    private function updateCompanyCompanyDomains(array $dataDomain, array $floodCompanyDomains, Lead $lead): array
    {
        $result = [];
        if (
            isset($dataDomain['details']['floodlight'])
            && $dataDomain['details']['floodlight'] instanceof FloodlightDTO
        ) {
            $floodlightDTO = $dataDomain['details']['floodlight'];
            assert($floodlightDTO instanceof FloodlightDTO);
            $this->fieldHelper->updateProperties($floodlightDTO);
            foreach ($floodCompanyDomains as $companyDomain) {
                assert($companyDomain instanceof FloodlightCompanyDomains);
                $company = $companyDomain->getCompany();
                $company = $floodlightDTO->fillCompany($company);
                $this->companyModel->saveEntity($company);
                $tempResult = $floodlightDTO->fillCompaniesDomain($this->floodlightCompanyDomainsModel, $company);
                $result     = array_merge($result, $tempResult);
            }
            if (!empty($result)) {
                $this->floodlightCompanyDomainsModel->saveEntities($result);
            }

            return $result;
        }

        foreach ($floodCompanyDomains as $companyDomain) {
            assert($companyDomain instanceof FloodlightCompanyDomains);
            $this->leadModel->addToCompany($lead, $companyDomain->getCompany());
            $result[] = $companyDomain;
        }
        if (!empty($result)) {
            $this->floodlightCompanyDomainsModel->saveEntities($result);

            return $result;
        }

        return [];
    }

    public function setChannel($output): void
    {
        $this->reporter->setChannel($output);
    }
}
