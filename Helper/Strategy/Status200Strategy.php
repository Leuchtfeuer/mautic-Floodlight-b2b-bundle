<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;

class Status200Strategy implements StatusCodeStrategyInterface
{
    public function __construct(
        private FloodlightLeadDomainsModel $leadDomainsModel,
        private FloodlightCompanyDomainsModel $companyDomainsModel,
        private CompanyRepository $companyRepository,
        private CompanyModel $companyModel,
        private LeadModel $leadModel,
        private FieldHelper $fieldHelper
    ) {}

    public function getStatusCode(): string
    {
        return '200';
    }

    public function prepareData(array $data): array|false
    {
        $floodlightDTO = new FloodlightDTO($data);

        $domain = $floodlightDTO->getCompanyPrimaryDomain();
        if (empty($domain) && !empty($floodlightDTO->getDomains())) {
            $domain = $floodlightDTO->getDomains()[0];
        }

        $status = $this->getStatusDomain($domain);

        return [
            'status'  => $status,
            //lookupType
            'domain'  => $this->cleanDomain($domain),
            'details' => [
                'floodlight'    => $floodlightDTO,
                'statusRequest' => $data['statusRequestCode'],
            ],
        ];
    }

    public function processData(array $processedData, $lead)
    {
        //check if domain is ip
        //check if ip is blacklisted
        $this->updateF4bLookupsTable($processedData, $lead);
        $this->leadDomainsModel->deleteOldRetryEntries($processedData['details']['floodlight'], $lead);

        $company = $this->companyRepository->findOneBy(['name' => $processedData['details']['floodlight']->getCompanyName()]);

        if(empty($company)) {
            $this->fieldHelper->updateProperties($processedData['details']['floodlight']);
            $company = $this->createNewCompany($processedData['details']['floodlight'], $lead);
        }
        else{
            $this->updateCompany($processedData['details']['floodlight'], $lead, $company);
        }

        $companyDomain = $this->companyDomainsModel->getExistingCompanyDomain($processedData['domain']);
        if (empty($companyDomain)) {
            $this->companyDomainsModel->createNewCompanyDomain($processedData['details']['floodlight'], $company);
        }
        else {
            //update Company Domain probably
        }

        $this->leadDomainsModel->recalculateLeadPrimaryCompany($lead);


        //update f4b company domains table (new entry or do nothing)
        //update companies table (new entry or update data) --> Make sure right merging logic is implemented
        //recalcutlate primary company of lead
        //Delete retry entry for iphash-lead-combination if already exists
    }

    public function updateF4bLookupsTable(array $processedData, $lead): void
    {
        $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $processedData);
    }

    public function createNewCompany(FloodlightDTO $floodlightDTO, Lead $lead): Company
    {
        $company       = $this->companyModel->getEntity();
        $company       = $floodlightDTO->fillCompany($company);
        $this->leadModel->addToCompany($lead, $company);
        $this->companyModel->saveEntity($company);
        return $company;
    }

    public function updateCompany(FloodlightDTO $floodlightDTO, Lead $lead, $company): void
    {
        $company = $floodlightDTO->fillCompany($company);
        $this->leadModel->addToCompany($lead, $company);
        $this->companyModel->saveEntity($company);

    }

    public function getStatusDomain(?string $domain): string
    {
        if (empty($domain)) {
            return FloodlightLeadDomains::STATUS_NO_DATA_FOUND;
        }

        /*
        if ($this->isIp($domain)) {
            return FloodlightLeadDomains::STATUS_IP_DOMAIN;
        }
        */

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
}