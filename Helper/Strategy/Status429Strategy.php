<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy;

use DateTime;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;

class Status429Strategy
{
    public function __construct(
        private readonly DomainUtility $domainUtility,
        private readonly FloodlightLeadDomainsModel $leadDomainsModel,
        private readonly CompanyRepository $companyRepository,
        private CompanyModel $companyModel,
        private readonly LeadModel $leadModel,
        private FloodlightCompanyDomainsModel $companyDomainsModel,
    )
    {}
    public function prepareData(string $remoteAddr): array
    {
        $hostByAddrData = $this->domainUtility->getDomainByGetHostByAddr($remoteAddr);
        if (FloodlightLeadDomains::STATUS_SUCCESS === $hostByAddrData['status']
        ) {
            $hostByAddrData['lookupType'] = FloodlightLeadDomains::LOOKUP_TYPE_DNS;
            return $hostByAddrData;
        }

        $pyServiceData = $this->domainUtility->getDomainByWhoisPyService($remoteAddr);
        if (FloodlightLeadDomains::STATUS_SUCCESS === $pyServiceData['status']
        ) {
            $pyServiceData['lookupType'] = FloodlightLeadDomains::LOOKUP_TYPE_WHOIS;
            return $pyServiceData;
        }

        return [];
    }

    public function process(array $processedData, $lead)
    {
        if(empty($processedData)){
            //$this->reporter->notify('quota exceeded and also no data found via DNS and Whois lookup');
            return;
        }

        $leadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $processedData);

        $company = $this->companyRepository->findOneBy(['name' => $processedData['domain']]);

        if(empty($company)) {
            $company = $this->createNewCompany($processedData['domain'], $processedData['details'], $lead);
        }


        $companyDomain = $this->companyDomainsModel->getExistingCompanyDomain($processedData['domain']);
        if (empty($companyDomain) && !empty($company)) {
            $this->createNewCompanyDomain($processedData['domain'], $company);
        }

        $this->leadDomainsModel->recalculateLeadPrimaryCompany($lead);

    }

    public function createNewCompany($domain, $details, Lead $lead): Company
    {
        //$userIds       = $this->notificationUserHelper->getAdminUsers();
        //$user          = $this->userModel->getEntity($userIds[0]);
        $company       = $this->companyModel->getEntity();

        $company->setName($domain);
        $company->setWebsite($domain);
        $company->setDateAdded(new \DateTime());
        $company->setIsPublished(true);
        //$company->setCreatedBy($user);
        //$company->setModifiedBy($user);
        //$company->setOwner($user);

        if (!empty($details) && isset($details['pyservice']) && is_array($details['pyservice'])) {
            $company->addUpdatedField('f4b_iplookup_whois_json', json_encode($this->transformDetails($details['pyservice']), JSON_THROW_ON_ERROR));
        }

        $this->companyModel->saveEntity($company);

        if (null !== $lead) {
            $this->leadModel->addToCompany($lead, $company);
            $this->leadModel->saveEntity($lead);
        }

        return $company;
    }

    public function updateCompany(FloodlightDTO $floodlightDTO, Lead $lead, $company): void
    {
        $company = $floodlightDTO->fillCompany($company);
        $this->leadModel->addToCompany($lead, $company);
        $this->companyModel->saveEntity($company);

    }


    /**
     * @param array<string> $details
     *
     * @return array<string>
     */
    private function transformDetails(array $details): array
    {
        return [
            'whois_company_name' => $details['company_name'],
            'whois_is_isp'       => $details['is_isp'],
            'whois_is_dialup'    => $details['is_dialup'],
        ];
    }

    private function createNewCompanyDomain($domain, Company $company): void
    {
        $companyDomain = new FloodlightCompanyDomains();
        $companyDomain->setDomain($domain);
        $companyDomain->setDateAdded(new \DateTime());
        $companyDomain->setDateModified(new \DateTime());
        $companyDomain->setIsPrimaryDomain(true);
        if (!empty($company->getId())) {
            $companyDomain->setCompany($company);
        }
        $this->companyDomainsModel->saveEntity($companyDomain);
    }
}