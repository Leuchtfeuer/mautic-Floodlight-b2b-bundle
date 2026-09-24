<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;

class DtoHelper
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
        private AuditLogHelper $auditLogHelper,
    ) {
    }

    public function cleanDtoFromNonFloodlightUpdatableFields(FloodlightDTO $floodlightDTO, Company $company): FloodlightDTO
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
        } catch (\ReflectionException) {
        }

        return '';
    }
}
