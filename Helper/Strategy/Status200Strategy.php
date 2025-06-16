<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy;

use DateTime;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
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
        private FieldHelper $fieldHelper,
        private DomainUtility $domainUtility
    ) {}

    public function getStatusCode(): string
    {
        return '200';
    }

    public function   prepareData(array $data): array|false
    {
        $floodlightDTO = new FloodlightDTO($data);

        $domain = $floodlightDTO->getCompanyPrimaryDomain();
        if (empty($domain) && !empty($floodlightDTO->getDomains())) {
            $domain = $floodlightDTO->getDomains()[0];
        }

        $status = $this->domainUtility->getStatusDomain($domain);

        return [
            'status'  => $status,
            'domain'  => $this->domainUtility->cleanDomain($domain),
            'lookupType' => FloodlightLeadDomains::LOOKUP_TYPE_API,
            'details' => [
                'floodlight'    => $floodlightDTO,
                'statusRequest' => $data['statusRequestCode'],
            ],
        ];
    }

    public function process(array $processedData, Lead $lead)
    {
        $leadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $processedData);
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

        $this->leadDomainsModel->recalculateLeadPrimaryCompany($lead);
    }

    private function createNewCompany(FloodlightDTO $floodlightDTO, Lead $lead): Company
    {
        $company       = $this->companyModel->getEntity();
        $company       = $floodlightDTO->fillCompany($company);
        $this->leadModel->addToCompany($lead, $company);
        $this->companyModel->saveEntity($company);
        return $company;
    }

    private function updateCompany(FloodlightDTO $floodlightDTO, Lead $lead, $company): void
    {
        $company = $floodlightDTO->fillCompany($company);
        $this->leadModel->addToCompany($lead, $company);
        $this->companyModel->saveEntity($company);

    }

}