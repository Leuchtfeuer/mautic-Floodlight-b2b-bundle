<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy;

use Mautic\CoreBundle\Helper\PathsHelper;
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
use Symfony\Component\HttpClient\HttpClient;

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
        private PathsHelper $pathsHelper,
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

        $domain                = $processedData['domain'];
        $floodlightDto         = $processedData['details']['floodlight'];
        $companyDomain         = $this->companyDomainsModel->getExistingCompanyDomain($processedData['domain']);
        $existingCompanyDomain = $companyDomain instanceof FloodlightCompanyDomains;
        if (FloodlightLeadDomains::STATUS_SUCCESS !== $processedData['status'] || '' === $domain) {
            return;
        }

        if (false === $this->config->getIgnoreTelcosSettings()
            || ($existingCompanyDomain && $this->companyHasKnownContacts($companyDomain->getCompany()))
            || 'Telecommunications' !== $floodlightDto->getCompanyIndustryICBCSector()
        ) {
            $this->processRegularCompany($floodlightDto, $domain, $lead);

            return;
        }

        if ($existingCompanyDomain && true === $companyDomain->getIsTelco()) {
            return;
        }

        if ($existingCompanyDomain && true !== $companyDomain->getIsTelco()) {
            $this->updateTelcoCompany($floodlightDto, $lead, $companyDomain->getCompany());

            return;
        }

        $existingCompanyWithName = $this->companyRepository->findOneBy(['name' => $floodlightDto->getCompanyName()]);
        if ($existingCompanyWithName instanceof Company) {
            $this->updateTelcoCompany($floodlightDto, $lead, $existingCompanyWithName);
        }
    }

    private function processRegularCompany(FloodlightDTO $floodlightDto, string $domain, Lead $lead): void
    {
        $companyDomain = $this->companyDomainsModel->getExistingCompanyDomain($domain);
        $company       = $this->companyRepository->findOneBy(['name' => $floodlightDto->getCompanyName()]);
        $this->fieldHelper->updateProperties($floodlightDto);

        if ($companyDomain instanceof FloodlightCompanyDomains) {
            $this->updateCompany($floodlightDto, $lead, $companyDomain->getCompany());
            $company = $companyDomain->getCompany();
        } elseif (null === $company) {
            $company = $this->createNewCompany($floodlightDto, $lead);
        } else {
            $this->updateCompany($floodlightDto, $lead, $company);
        }

        \assert($company instanceof Company);
        $this->updateRegularCompanyDomains($floodlightDto, $company);

        $this->leadDomainsModel->recalculateLeadPrimaryCompany($lead);
    }

    public function companyHasKnownContacts(?Company $company): bool
    {
        if (null === $company) {
            return false;
        }
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
        $this->processCompanyLogo($company, $floodlightDTO->getCompanyLogoUrl() ?? '');
        $this->companyModel->saveEntity($company);
        $this->leadModel->addToCompany($lead, $company);

        return $company;
    }

    private function updateCompany(FloodlightDTO $floodlightDTO, Lead $lead, Company $company): void
    {
        $floodlightDTO = $this->dtoHelper->cleanDtoFromNonFloodlightUpdatableFields($floodlightDTO, $company);
        $company       = $floodlightDTO->fillCompany($company);
        $this->processCompanyLogo($company, $floodlightDTO->getCompanyLogoUrl() ?? '');
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

    public function updateTelcoCompany(FloodlightDTO $floodlightDTO, Lead $lead, Company $company): void
    {
        $this->fieldHelper->updateProperties($floodlightDTO);
        $floodlightDTO = $this->dtoHelper->cleanDtoFromNonFloodlightUpdatableFields($floodlightDTO, $company);
        $this->updateCompany($floodlightDTO, $lead, $company);
        $this->detachAllUnknowContactsFromCompany($company);
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

    public function updateRegularCompanyDomains(FloodlightDTO $floodlightDto, Company $company): void
    {
        $this->companyDomainsModel->createNewCompanyDomain($floodlightDto, $company);
        $companyDomains = $this->companyDomainsModel->getCompanyDomainsEntriesOfCompany($company->getId());
        foreach ($companyDomains as $companyDomain) {
            $companyDomain->setIsTelco(false);
        }
        $this->companyDomainsModel->saveEntities($companyDomains);
    }

    private function processCompanyLogo(Company $company, string $companyLogoUrl): Company
    {
        if ($this->isLogoAlreadySetManually($company) || $companyLogoUrl === '') {
            return $company;
        }
        $fileName = $this->createLogoFile($company, $companyLogoUrl);
        return $this->updateLogoFilenameInCompany($company, $fileName);
    }

    private function isLogoAlreadySetManually(Company $company): bool {
        $logoFileName = $company->getFieldValue('companylogo_filename');
        if (str_starts_with($logoFileName, 'f4b_') || empty($logoFileName)) {
            return false;
        }
        return true;

    }

    private function createLogoFile(Company $company, string $companyLogoUrl): string
    {

        //filename = f4b_<md5(companyname+timestamp)>
        $timestamp = (new \DateTime())->format('Y-m-d-H-i-s');
        $logoFileName = 'f4b_'.md5($company->getName().$timestamp);

        //filetype = curl request on logofile --> type herausfinden (companyLogoUrl)
        $httpClient = HttpClient::create();
        $response = $httpClient->request('GET', $companyLogoUrl);
        if ($response->getStatusCode() !== 200) {
            throw new \Exception("Failed to download logo from: {$companyLogoUrl}");
        }

        $imageData = $response->getContent();
        $contentType = $response->getHeaders()['content-type'][0] ?? '';
        $fileExtension = '';
        if (str_contains($contentType, 'image/png')) {
            $fileExtension = '.png';
        } elseif (str_contains($contentType, 'image/jpeg') || str_contains($contentType, 'image/jpg')) {
            $fileExtension = '.jpg';
        } else {
            throw new \Exception("Unsupported image type: {$contentType}");
        }

        //createFile
        $fullFileName = $logoFileName . $fileExtension;
        $filePath = $this->pathsHelper->getMediaPath().'/logos/'.$fullFileName;
        if (file_put_contents($filePath, $imageData) === false) {
            throw new \Exception("Failed to save logo file: {$filePath}");
        }

        return $fullFileName;
    }

    private function updateLogoFilenameInCompany(Company $company, string $fileName): Company
    {
        if ($company->getFieldValue('companylogo_filename') === $fileName) {
            return $company;
        }

        $company->addUpdatedField('companylogo_filename', $fileName);
        return $company;
    }
}
