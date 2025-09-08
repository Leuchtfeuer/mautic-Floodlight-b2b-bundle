<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDtoFactory;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DtoHelper;
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
        private DomainUtility $domainUtility,
        private FloodlightDtoFactory $dtoFactory,
        private DtoHelper $dtoHelper,
    ) {
    }

    public function getStatusCode(): string
    {
        return '200';
    }

    /**
     * @param mixed[] $data
     *
     * @return array{
     *     status: string,
     *     domain: string,
     *     lookupType: string,
     *     details: array{
     *         floodlight: FloodlightDTO,
     *         statusRequest: mixed
     *     }
     * }
     */
    public function prepareData(array $data): array
    {
        $floodlightDTO = $this->dtoFactory->create($data);

        $domain = $floodlightDTO->getCompanyPrimaryDomain();
        if (empty($domain) && !empty($floodlightDTO->getDomains())) {
            $domain = $floodlightDTO->getDomains()[0];
        }

        $status = $this->domainUtility->getStatusDomain($domain);

        return [
            'status'     => $status,
            'domain'     => $this->domainUtility->cleanDomain($domain) ?? '',
            'lookupType' => FloodlightLeadDomains::LOOKUP_TYPE_API,
            'details'    => [
                'floodlight'    => $floodlightDTO,
                'statusRequest' => $data['statusRequestCode'],
            ],
        ];
    }

    /**
     * @param array{
     *      status: string,
     *      domain: string,
     *      lookupType: string,
     *      details: array{
     *          floodlight: FloodlightDTO,
     *          statusRequest: mixed
     *      }
     *  } $processedData
     */
    public function process(array $processedData, Lead $lead): void
    {
                //process lead domain --> normal
                //Process company domain --> is_telco=1 (all company domains)
                //Process company

                    //detach all unknown contacts from company
                    //Create company and set unpublished

        //Fall 1
        //ziehe alle companyDomains mit dieser domain
        // jüngst company domain ist telco + keine der mit domain verbundenen Companies hat known contact
        // Bleiben lassen

        //Falls 2
        // no companyDomain und Telecommunication Sector
        // f4b_lead_ip_lookups wird normal verarbeitet
        // companyDomains werden angelegt mit is_telco = 1
        // company
        // Erstellen oder updaten (wie sonst auch)
        // Detach unknown contacts
        // unpublished setzen
        //    was wenn schon eine existiert (mit selben Namen)?


        //    eine company oder alle?

        $leadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $processedData);
        $this->leadDomainsModel->deleteOldRetryEntries($leadDomain, $lead);

        if (FloodlightLeadDomains::STATUS_SUCCESS !== $processedData['status'] || '' === $processedData['domain']) {
            return;
        }

        $companyDomain = $this->companyDomainsModel->getExistingCompanyDomain($processedData['domain']);
        $company       = $this->companyRepository->findOneBy(['name' => $processedData['details']['floodlight']->getCompanyName()]);

        if (!empty($companyDomain)) {
            \assert($companyDomain[0] instanceof FloodlightCompanyDomains);
            $company = $companyDomain[0]->getCompany();
            $this->updateCompany($processedData['details']['floodlight'], $lead, $company);
        }

        if (empty($company)) {
            $this->fieldHelper->updateProperties($processedData['details']['floodlight']);
            $company = $this->createNewCompany($processedData['details']['floodlight'], $lead);
        } else {
            $this->updateCompany($processedData['details']['floodlight'], $lead, $company);
        }

        if (empty($companyDomain)) {
            $this->companyDomainsModel->createNewCompanyDomain($processedData['details']['floodlight'], $company);
        }

        $this->leadDomainsModel->recalculateLeadPrimaryCompany($lead);
    }

    private function createNewCompany(FloodlightDTO $floodlightDTO, Lead $lead): Company
    {
        $company       = $this->companyModel->getEntity();
        \assert($company instanceof Company);
        $company       = $floodlightDTO->fillCompany($company);
        $this->companyModel->saveEntity($company);
        $this->leadModel->addToCompany($lead, $company);

        return $company;
    }

    private function updateCompany(FloodlightDTO $floodlightDTO, Lead $lead, Company $company): void
    {
        $floodlightDTO = $this->dtoHelper->cleanDtoFromNonFloodlightUpdatableFields($floodlightDTO, $company);
        $company       = $floodlightDTO->fillCompany($company);
        $this->companyModel->saveEntity($company);
        $this->leadModel->addToCompany($lead, $company);
    }

    private function createTelcoCompany(): void
    {

    }

    public function detachAllUnknowContactsFromCompany(Company $company): void
    {
        $leads = $this->companyLeadRepository->getCompanyLeads($company->getId());
        foreach ($leads as $lead) {
            if ($this->leadIsAnonymous($lead) === true) {
                $this->companyModel->removeLeadFromCompany($company, $lead);
                $this->companyModel->saveEntity($company);
                $this->leadModel->saveEntity($lead);
            }
        }

    }

    public function leadIsAnonymous(Lead $lead): bool
    {
        return !($lead->getFirstname() || $lead->getLastname() || $lead->getEmail());
    }

    public function processTelcoCompany(){

    }

    /*
    public function process(array $processedData, Lead $lead): void
    {
        $companyDomains = $this->companyDomainsModel->getExistingCompanyDomain($processedData['domain']);
        $icbSector = $processedData['details']['floodlight']->getCompanyIndustryICBCSector();
        if($this->config->getIgnoreTelcosSettings() === true ) {
            //$companyDomain = companyDomains mit jünsten date modified
            //$company = companyDomain->getcompany
            if (!empty($companyDomains) && $companyDomain-->isTelco() && $this->checkIfKnownContactInCompany($companyDomains)) { //isTelcoCompanyWithNoKnownContact
                return;
            }
            if (empty($companyDomains) && $processedData['details']['floodlight']->getCompanyIndustryICBCSector() === 'Telecommunications') {
                $this->processLeadDomain($lead, $processedData);
                $this->createTelcoCompany();
                $this->

                //process lead domain --> normal
                //Process company domain --> is_telco=1 (all company domains)
                //Process company

                    //detach all unknown contacts from company
                    //Create company and set unpublished
            }
        }

        //Fall 1
        //ziehe alle companyDomains mit dieser domain
        // jüngst company domain ist telco + keine der mit domain verbundenen Companies hat known contact
        // Bleiben lassen

        //Falls 2
        // no companyDomain und Telecommunication Sector
        // f4b_lead_ip_lookups wird normal verarbeitet
        // companyDomains werden angelegt mit is_telco = 1
        // company
        // Erstellen oder updaten (wie sonst auch)
        // Detach unknown contacts
        // unpublished setzen
        //    was wenn schon eine existiert (mit selben Namen)?

        //processleaddomain(lead, $data)
        $leadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $processedData);
        $this->leadDomainsModel->deleteOldRetryEntries($leadDomain, $lead);

        if (FloodlightLeadDomains::STATUS_SUCCESS !== $processedData['status'] || '' === $processedData['domain']) {
            return;
        }

        //companyDomainZiehen
        //notempty
        //updateCompanyIfExistingCompanyDomain
        //updateOrCreateCompany

        $companyDomain = $this->companyDomainsModel->getExistingCompanyDomain($processedData['domain']);
        $company       = $this->companyRepository->findOneBy(['name' => $processedData['details']['floodlight']->getCompanyName()]);

        if (!empty($companyDomain)) {
            \assert($companyDomain[0] instanceof FloodlightCompanyDomains);
            $company = $companyDomain[0]->getCompany();
            //    eine company oder alle?
            $this->updateCompany($processedData['details']['floodlight'], $lead, $company);
        }

        if (empty($company)) {
            $this->fieldHelper->updateProperties($processedData['details']['floodlight']);
            $company = $this->createNewCompany($processedData['details']['floodlight'], $lead);
        } else {
            $this->updateCompany($processedData['details']['floodlight'], $lead, $company);
        }

        if (empty($companyDomain)) {
            $this->companyDomainsModel->createNewCompanyDomain($processedData['details']['floodlight'], $company);
        }

        $this->leadDomainsModel->recalculateLeadPrimaryCompany($lead);
    }

    private function processLeadDomain($lead, $processedData): void
    {
        $leadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $processedData);
        $this->leadDomainsModel->deleteOldRetryEntries($leadDomain, $lead);
    }

    private function updateOrCreateCompany($processedData, Lead $lead, ?Company $company): void
    {
        if (empty($company)) {
            $this->fieldHelper->updateProperties($processedData['details']['floodlight']);
            $company = $this->createNewCompany($processedData['details']['floodlight'], $lead);
        } else {
            $this->updateCompany($processedData['details']['floodlight'], $lead, $company);
        }
    }
    */


}
