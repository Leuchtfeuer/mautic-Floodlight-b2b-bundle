<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDtoFactory;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\AuditLogHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;

class Status200Strategy implements StatusCodeStrategyInterface
{
    // dbFieldName => dtoPropertyName
    private const FIELD_MAPPING = [
        'f4b_companytechnologies'               => 'companyTechnologies',
        'f4b_companylabels'                     => 'companyLabels',
        'f4b_companyindustry_linkedin'          => 'companyIndustryLinkedin',
        'f4b_companyindustry_list'              => 'companyIndustryList',
        'f4b_companydescription'                => 'companyDescription',
        'f4b_companydescription_long'           => 'companyDescriptionLong',
        'f4b_companyname_legal'                 => 'companyNameLegal',
        'f4b_companyname_alternatives'          => 'companyNameAlternative',
        'f4b_companyfloodlight_id'              => 'companyFloodlightId',
        'f4b_companycrunchbase_handle'          => 'companyCrunchbaseHandle',
        'f4b_companyindustry_naicscode'         => 'companyIndustryNaicsCode',
        'f4b_companyindustry_icb_industrygroup' => 'companyIndustryICBIndustryGroup',
        'f4b_companyindustry_icb_industry'      => 'companyIndustryICBCIndustry',
        'f4b_companyindustry_icb_subIndustry'   => 'companyIndustryICBCSubIndustry',
        'f4b_companyindustry_icb_sector'        => 'companyIndustryICBCSector',
        'f4b_companyfounding_year'              => 'companyFoundingYear',
        'f4b_companycountry_code'               => 'companyCountryCode',
        'f4b_companyrank_tranco'                => 'companyRankTranco',
        'f4b_companyemployees_range'            => 'companyEmployeeRange',
        'f4b_companymarketcap'                  => 'companyMarketCap',
        'f4b_companyannual_revenue_range'       => 'companyAnnualRevenueRange',
        'f4b_companyfunding_raised'             => 'companyFundingRaised',
        'f4b_companyfacebook_handle'            => 'companyFacebookHandle',
        'f4b_companytwitter_handle'             => 'companyTwitterHandle',
        'f4b_companylinkedin_handle'            => 'companyLinkedinHandle',
        'f4b_companytwitter_followers'          => 'companyTwitterFollowers',
        'f4b_companylogo'                       => 'companyLogoUrl',
        'f4b_companytype'                       => 'companyType',
        'f4b_companyticker'                     => 'companyTicker',
        'f4b_companytimezone'                   => 'companyTimezone',
        'f4b_bigpicture_id'                     => 'bigPictureId',
        'f4b_companystate_code'                 => 'companyStateCode',
        'companyname'                           => 'companyName',
        'companycity'                           => 'companyCity',
        'companystate'                          => 'companyState',
        'companycountry'                        => 'companyCountry',
        'companywebsite'                        => 'companyWebsite',
        'companyemail'                          => 'companyEmail',
        'companyindustry'                       => 'companyIndustry',
        'companyaddress1'                       => 'companyAddress1',
        'companyaddress2'                       => 'companyAddress2',
        'companyzipcode'                        => 'companyZipCode',
        'companyphone'                          => 'companyPhone',
        'companynumber_of_employees'            => 'companyNumberOfEmployees',
    ];

    public function __construct(
        private FloodlightLeadDomainsModel $leadDomainsModel,
        private FloodlightCompanyDomainsModel $companyDomainsModel,
        private CompanyRepository $companyRepository,
        private CompanyModel $companyModel,
        private LeadModel $leadModel,
        private FieldHelper $fieldHelper,
        private DomainUtility $domainUtility,
        private FloodlightDtoFactory $dtoFactory,
        private AuditLogHelper $auditLogHelper,
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

        $company = $this->companyRepository->findOneBy(['name' => $processedData['details']['floodlight']->getCompanyName()]);

        if (empty($company)) {
            $this->fieldHelper->updateProperties($processedData['details']['floodlight']);
            $company = $this->createNewCompany($processedData['details']['floodlight'], $lead);
        } else {
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
        \assert($company instanceof Company);
        $company       = $floodlightDTO->fillCompany($company);
        $this->companyModel->saveEntity($company);
        $this->leadModel->addToCompany($lead, $company);

        return $company;
    }

    private function updateCompany(FloodlightDTO $floodlightDTO, Lead $lead, Company $company): void
    {
        $floodlightDTO = $this->cleanDtoFromNonFloodlightUpdatableFields($floodlightDTO, $company);
        $company       = $floodlightDTO->fillCompany($company);
        $this->companyModel->saveEntity($company);
        $this->leadModel->addToCompany($lead, $company);
    }

    private function cleanDtoFromNonFloodlightUpdatableFields(FloodlightDTO $floodlightDTO, Company $company): FloodlightDTO
    {
        $nonUpdatableFields = $this->auditLogHelper->getNonFloodlightUpdatableFields($company->getId());
        $reflectionClass    = new \ReflectionClass($floodlightDTO);
        foreach ($nonUpdatableFields as $field) {
            $dtoProperty = self::FIELD_MAPPING[$field] ?? $field;
            if (property_exists($floodlightDTO, $dtoProperty)) {
                $setterName   = 'set'.ucfirst($dtoProperty);
                $defaultValue = $this->getDefaultValueForProperty($reflectionClass, $dtoProperty);
                $floodlightDTO->$setterName($defaultValue);
            }
        }

        return $floodlightDTO;
    }

    private function getDefaultValueForProperty(\ReflectionClass $reflectionClass, string $propertyName): mixed
    {
        try {
            $property = $reflectionClass->getProperty($propertyName);
            $type     = $property->getType();

            if ($type instanceof \ReflectionNamedType) {
                $typeName = $type->getName();

                // Handle nullable types
                if ($type->allowsNull() && 'int' === $typeName) {
                    return null;
                }

                return match ($typeName) {
                    'string' => '',
                    'int'    => null,
                    'array'  => [],
                    default  => null
                };
            }
        } catch (\ReflectionException $e) {
        }

        return '';
    }
}
