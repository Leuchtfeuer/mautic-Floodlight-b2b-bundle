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
        $domain        = $processedData['domain'];
        $floodlightDto = $processedData['details']['floodlight'];

        if (FloodlightLeadDomains::STATUS_SUCCESS !== $processedData['status'] || '' === $domain) {
            return;
        }

        if (false === $this->config->getIgnoreTelcosSettings()) {
            $this->processRegularCompany($floodlightDto, $domain, $lead);

            return;
        }

        $companyDomain         = $this->companyDomainsModel->getExistingCompanyDomain($processedData['domain']);
        $existingCompanyDomain = $companyDomain instanceof FloodlightCompanyDomains;

        if ($existingCompanyDomain && $this->companyHasKnownContacts($companyDomain->getCompany())) {
            $this->processRegularCompany($floodlightDto, $domain, $lead);

            return;
        }

        if ($existingCompanyDomain && true === $companyDomain->getIsTelco()) {
            return;
        }

        if ($existingCompanyDomain && true !== $companyDomain->getIsTelco()) {
            $icbSector = $floodlightDto->getCompanyIndustryICBCSector();
            if ('Telecommunications' === $icbSector) {
                $this->updateTelcoCompany($floodlightDto, $lead, $companyDomain->getCompany());

                return;
            } else {
                $this->processRegularCompany($floodlightDto, $domain, $lead);

                return;
            }
        }

        if (!$existingCompanyDomain) {
            $icbSector = $floodlightDto->getCompanyIndustryICBCSector();
            if ('Telecommunications' === $icbSector) {
                $shouldBeUnpublished     = empty($this->companyRepository->findOneBy(['name' => $floodlightDto->getCompanyName()]));
                $company                 = $this->createTelcoCompanyOrReturnExistingCompany($floodlightDto);
                $this->updateTelcoCompany($floodlightDto, $lead, $company, $shouldBeUnpublished);
            } else {
                $this->processRegularCompany($floodlightDto, $domain, $lead);
            }
        }
    }

    private function processRegularCompany(FloodlightDTO $floodlightDto, string $domain, Lead $lead): void
    {
        $companyDomain = $this->companyDomainsModel->getExistingCompanyDomain($domain);
        $company       = $this->companyRepository->findOneBy(['name' => $floodlightDto->getCompanyName()]);
        $this->fieldHelper->updateProperties($floodlightDto);

        if ($companyDomain instanceof FloodlightCompanyDomains) {
            $this->updateCompany($floodlightDto, $lead, $companyDomain->getCompany());
        } elseif (empty($company)) {
            $company = $this->createNewCompany($floodlightDto, $lead);
        } else {
            $this->updateCompany($floodlightDto, $lead, $company);
        }

        if (null === $companyDomain) {
            \assert($company instanceof Company);
            $this->companyDomainsModel->createNewCompanyDomain($floodlightDto, $company);
        }

        $this->leadDomainsModel->recalculateLeadPrimaryCompany($lead);
    }

    public function companyHasKnownContacts(Company $company): bool
    {
        $leadIds = $this->companyLeadRepository->getCompanyLeads($company->getId());
        $leads   = $this->leadModel->getRepository()->findBy(['id' => array_column($leadIds, 'lead_id')]);
        foreach ($leads as $lead) {
            if (false === $this->leadIsAnonymous($lead)) {
                return true;
            }
        }

        return false;
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
        $leads    = $this->leadModel->getRepository()->findBy(['id' => array_column($leadsIds, 'lead_id')]);
        foreach ($leads as $lead) {
            if (true === $this->leadIsAnonymous($lead)) {
                $this->companyModel->removeLeadFromCompany($company, $lead);
                $this->companyModel->saveEntity($company);
                $this->leadModel->saveEntity($lead);
            }
        }
    }

    private function leadIsAnonymous(Lead $lead): bool
    {
        return !($lead->getFirstname() || $lead->getLastname() || $lead->getEmail());
    }

    public function updateTelcoCompany(FloodlightDTO $floodlightDTO, Lead $lead, Company $company, bool $shouldUnpublish = true): void
    {
        $this->fieldHelper->updateProperties($floodlightDTO);
        $floodlightDTO = $this->dtoHelper->cleanDtoFromNonFloodlightUpdatableFields($floodlightDTO, $company);
        $this->updateCompany($floodlightDTO, $lead, $company);
        $this->detachAllUnknowContactsFromCompany($company);
        if (true === $shouldUnpublish) {
            $company->setIsPublished(false);
        }
        $this->companyModel->saveEntity($company);

        $this->updateTelcoCompanyDomains($floodlightDTO, $company);
    }

    public function updateTelcoCompanyDomains(FloodlightDTO $floodlightDto, Company $company): void
    {
        $this->companyDomainsModel->createNewCompanyDomain($floodlightDto, $company);
        $companyDomains = $this->companyDomainsModel->getCompanyDomainsEntriesOfCompany($company->getId());
        foreach ($companyDomains as $companyDomain) {
            $companyDomain->setIsTelco(true);
        }
        $this->companyDomainsModel->saveEntities($companyDomains);
    }

    private function createTelcoCompanyOrReturnExistingCompany(FloodlightDTO $floodlightDto): Company
    {
        $company       = $this->companyRepository->findOneBy(['name' => $floodlightDto->getCompanyName()]);
        if (empty($company)) {
            $company       = $this->companyModel->getEntity();
            \assert($company instanceof Company);
            $company       = $floodlightDto->fillCompany($company);
            $company->setIsPublished(false);
            $this->companyModel->saveEntity($company);

            return $company;
        }

        return $company;
    }
}
