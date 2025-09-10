<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
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
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
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
        private Config $config,
        private CompanyLeadRepository $companyLeadRepository,
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
        $leadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $processedData);
        $this->leadDomainsModel->deleteOldRetryEntries($leadDomain, $lead);

        if (FloodlightLeadDomains::STATUS_SUCCESS !== $processedData['status'] || '' === $processedData['domain']) {
            return;
        }

        if($this->config->getIgnoreTelcosSettings() === false ) {
            $this->processRegularCompany($processedData, $lead);
            return;
        } //done

        $companyDomain = $this->companyDomainsModel->getExistingCompanyDomain($processedData['domain']);
        $existingCompanyDomain = ($companyDomain instanceof FloodlightCompanyDomains);
        $existingCompany = $this->companyRepository->findOneBy(['name' => $processedData['details']['floodlight']->getCompanyName()]);

        if($existingCompanyDomain && $this->companyHasKnownContacts($companyDomain->getCompany())){
            $this->processRegularCompany($processedData, $lead);
            return;
        }

        if($existingCompanyDomain && $companyDomain->getIsTelco() === true){
            return;
        } //done

        if($existingCompanyDomain && $companyDomain->getIsTelco() !== true){
            $icbSector = $processedData['details']['floodlight']->getCompanyIndustryICBCSector();
            if($icbSector === 'Telecommunications'){
                $this->updateTelcoCompany($processedData['details']['floodlight'], $lead, $companyDomain->getCompany());
                return; //done
            } else {
                $this->processRegularCompany($processedData, $lead);
                return;  //done
            }
        }

        if(!$existingCompanyDomain){
            $icbSector = $processedData['details']['floodlight']->getCompanyIndustryICBCSector();
            if ($icbSector === 'Telecommunications'){
                $this->createOrUpdateTelcoCompany($processedData, $lead);
            } else {
                $this->processRegularCompany($processedData, $lead);
            }
        }
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
    private function processRegularCompany(array $processedData, Lead $lead): void
    {
        $companyDomain = $this->companyDomainsModel->getExistingCompanyDomain($processedData['domain']);
        $company       = $this->companyRepository->findOneBy(['name' => $processedData['details']['floodlight']->getCompanyName()]);
        $this->fieldHelper->updateProperties($processedData['details']['floodlight']);

        if ($companyDomain instanceof FloodlightCompanyDomains) {
            $this->updateCompany($processedData['details']['floodlight'], $lead, $companyDomain->getCompany());
        }
        elseif (empty($company)) {
            $company = $this->createNewCompany($processedData['details']['floodlight'], $lead);
        } else {
            $this->updateCompany($processedData['details']['floodlight'], $lead, $company);
        }

        if (null === $companyDomain) {
            $this->companyDomainsModel->createNewCompanyDomain($processedData['details']['floodlight'], $company);
        }

        $this->leadDomainsModel->recalculateLeadPrimaryCompany($lead);
    }

    private function companyHasKnownContacts(Company $company): bool
    {
        $leadIds = $this->companyLeadRepository->getCompanyLeads($company->getId());
        $leads = $this->leadModel->getRepository()->findBy(['id' => array_column($leadIds, 'lead_id')]);
        foreach ($leads as $lead) {
            if ($this->leadIsAnonymous($lead) === false) {
                return true;
            }
        }
        return false;
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
    public function oldProcess(array $processedData, Lead $lead): void
    {

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

    public function detachAllUnknowContactsFromCompany(Company $company): void
    {
        $leadsIds = $this->companyLeadRepository->getCompanyLeads($company->getId());
        $leads = $this->leadModel->getRepository()->findBy(['id' => array_column($leadsIds, 'lead_id')]);
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

    private function updateTelcoCompany(FloodlightDTO $floodlightDTO, Lead $lead, Company $company): void
    {
        $this->fieldHelper->updateProperties($floodlightDTO);
        $floodlightDTO = $this->dtoHelper->cleanDtoFromNonFloodlightUpdatableFields($floodlightDTO, $company);
        $this->updateCompany($floodlightDTO, $lead, $company);
        $this->detachAllUnknowContactsFromCompany($company);
        $company->setIsPublished(false);
        $this->companyModel->saveEntity($company);

        $this->updateTelcoCompanyDomains($floodlightDTO, $company);


    }

    private function createOrUpdateTelcoCompany(array $processedData, Lead $lead): Company
    {
        $company       = $this->companyRepository->findOneBy(['name' => $processedData['details']['floodlight']->getCompanyName()]);
        if (empty($company)) {
            $company       = $this->companyModel->getEntity();
            \assert($company instanceof Company);
            $company       = $processedData['details']['floodlight']->fillCompany($company);
            $company->setIsPublished(false);
            $this->companyModel->saveEntity($company);
        } else {
            $this->updateTelcoCompany($processedData['details']['floodlight'], $lead, $company);
        }
        $this->updateTelcoCompanyDomains($processedData['details']['floodlight'], $company);

        return $company;
    }

    private function updateTelcoCompanyDomains($floodlightDto, $company): void{
        $companyDomains = $this->companyDomainsModel->createNewCompanyDomain($floodlightDto, $company);
        $companyDomains = $this->companyDomainsModel->getCompanyDomainsEntriesOfCompany($company->getId());
        foreach ($companyDomains as $companyDomain) {
            $companyDomain->setIsTelco(true);
        }
        $this->companyDomainsModel->saveEntities($companyDomains);
    }

    private function unpublishTelcoCompany($company){

    }
}
