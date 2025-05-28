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
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status202Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status401Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status404Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status429Strategy;

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
        private LeadModel $leadModel,
        private Reporter $reporter,
        private Status200Strategy $status200Strategy,
        private DomainUtility $domainUtility,
        private Status202Strategy $status202Strategy,
        private Status401Strategy $status401Strategy,
        private Status404Strategy $status404Strategy,
        private Status429Strategy $status429Strategy,

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
        $status = $this->domainUtility->getStatusDomain($domain);

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
            'domain'  => $this->domainUtility->cleanDomain($domain),
            'details' => [
                'floodlight'    => $floodlightDTO,
                'statusRequest' => $data['statusRequestCode'],
            ],
        ];
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
    public function getAndProcessFloodlightApiData(string $remoteAddr, $lead): void
    {

        $data = $this->floodlightService->get($remoteAddr);


        if (!$this->checkIfInListOfValidRequestStatuses($data)) {
            // invalidStatusStrategy
            //@TODO: should there be logged anything?
        }

        if($data['statusRequestCode'] == 200){
            $processData = $this->status200Strategy->prepareData($data);
            $this->status200Strategy->process($processData, $lead);
        }

        if($data['statusRequestCode'] == 202){
            $processData = $this->status202Strategy->prepareData($data);
            $this->status202Strategy->process($processData, $lead);
        }

        if($data['statusRequestCode'] == 404){
            $processData = $this->status404Strategy->prepareData($data);
            $this->status404Strategy->process($processData, $lead);
        }

        if($data['statusRequestCode'] == 429){
            $processData = $this->status429Strategy->prepareData($remoteAddr);
            $this->status429Strategy->process($processData, $lead);
        }

        if($data['statusRequestCode'] == 401) {
            $this->reporter->notify('Floodlight API returned 401 Unauthorized. Please check your API credentials.');
        }

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
                $tempResult = $floodlightDTO->fillCompaniesDomain($this->companyDomainsModel, $company);
                $result     = array_merge($result, $tempResult);
            }
            if (!empty($result)) {
                $this->companyDomainsModel->saveEntities($result);
            }

            return $result;
        }

        foreach ($floodCompanyDomains as $companyDomain) {
            assert($companyDomain instanceof FloodlightCompanyDomains);
            $this->leadModel->addToCompany($lead, $companyDomain->getCompany());
            $result[] = $companyDomain;
        }
        if (!empty($result)) {
            $this->companyDomainsModel->saveEntities($result);

            return $result;
        }

        return [];
    }

    public function setChannel($output): void
    {
        $this->reporter->setChannel($output);
    }
}
