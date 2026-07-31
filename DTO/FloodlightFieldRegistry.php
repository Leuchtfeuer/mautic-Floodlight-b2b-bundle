<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

final class FloodlightFieldRegistry
{
    /** @var array<FieldDefinition>|null */
    private static ?array $definitions = null;

    /** @var array<string, FieldDefinition>|null */
    private static ?array $byApiKey = null;

    /** @var array<string, FieldDefinition>|null */
    private static ?array $byDbAlias = null;

    /** @var array<string, FieldDefinition>|null */
    private static ?array $byDtoProperty = null;

    /**
     * @return array<FieldDefinition>
     */
    public static function getDefinitions(): array
    {
        if (null === self::$definitions) {
            self::$definitions = self::buildDefinitions();
        }

        return self::$definitions;
    }

    public static function getByApiKey(string $apiKey): ?FieldDefinition
    {
        if (null === self::$byApiKey) {
            self::$byApiKey = [];
            foreach (self::getDefinitions() as $def) {
                self::$byApiKey[$def->apiKey] = $def;
            }
        }

        return self::$byApiKey[$apiKey] ?? null;
    }

    public static function getByDbAlias(string $dbAlias): ?FieldDefinition
    {
        if (null === self::$byDbAlias) {
            self::$byDbAlias = [];
            foreach (self::getDefinitions() as $def) {
                self::$byDbAlias[$def->dbAlias] = $def;
            }
        }

        return self::$byDbAlias[$dbAlias] ?? null;
    }

    public static function getByDtoProperty(string $dtoProperty): ?FieldDefinition
    {
        if (null === self::$byDtoProperty) {
            self::$byDtoProperty = [];
            foreach (self::getDefinitions() as $def) {
                self::$byDtoProperty[$def->dtoProperty] = $def;
            }
        }

        return self::$byDtoProperty[$dtoProperty] ?? null;
    }

    /**
     * @return array<FieldDefinition>
     */
    private static function buildDefinitions(): array
    {
        $s   = FieldType::STRING;
        $i   = FieldType::INT;
        $a   = FieldType::ARRAY;
        $lv  = FieldType::LIST_VALUE;
        $la  = FieldType::LIST_ARRAY;
        $ds  = CompanyMappingType::DIRECT_SETTER;
        $auf = CompanyMappingType::ADD_UPDATED_FIELD;
        $dau = CompanyMappingType::DIRECT_SETTER_AND_UPDATED_FIELD;
        $no  = CompanyMappingType::NONE;

        // apiKey, dbAlias, dtoProperty, type, companyMapping, companySetter, truncate
        return [
            // --- Core company name fields ---
            new FieldDefinition('companyname', 'companyname', 'companyName', $s, $ds, 'setName'),
            new FieldDefinition('companyname_legal', 'f4b_companyname_legal', 'companyNameLegal', $s, $auf),
            new FieldDefinition('companyname_alternatives', 'f4b_companyname_alternatives', 'companyNameAlternative', $a, $auf),

            // --- Identifiers ---
            new FieldDefinition('companyfloodlight_id', 'f4b_companyfloodlight_id', 'companyFloodlightId', $s, $auf),
            new FieldDefinition('bigpicture_id', 'f4b_bigpicture_id', 'bigPictureId', $s, $auf),
            new FieldDefinition('companycrunchbase_handle', 'f4b_companycrunchbase_handle', 'companyCrunchbaseHandle', $s, $auf),

            // --- Contact info ---
            new FieldDefinition('companywebsite', 'companywebsite', 'companyWebsite', $s, $ds, 'setWebsite'),
            new FieldDefinition('companyemail', 'companyemail', 'companyEmail', $s, $ds, 'setEmail'),
            new FieldDefinition('companyphone', 'companyphone', 'companyPhone', $s, $ds, 'setPhone'),

            // --- Address fields ---
            new FieldDefinition('companyaddress1', 'companyaddress1', 'companyAddress1', $s, $ds, 'setAddress1'),
            new FieldDefinition('companyaddress2', 'companyaddress2', 'companyAddress2', $s, $ds, 'setAddress2'),
            new FieldDefinition('companycity', 'companycity', 'companyCity', $s, $ds, 'setCity'),
            new FieldDefinition('companystate', 'companystate', 'companyState', $s, $ds, 'setState'),
            new FieldDefinition('companycountry', 'companycountry', 'companyCountry', $s, $ds, 'setCountry'),
            new FieldDefinition('companycountry_code', 'f4b_companycountry_code', 'companyCountryCode', $s, $auf),
            new FieldDefinition('companyzipcode', 'companyzipcode', 'companyZipCode', $s, $ds, 'setZipcode'),
            new FieldDefinition('companystate_code', 'f4b_companystate_code', 'companyStateCode', $s, $auf),

            // --- Industry fields ---
            new FieldDefinition('companyindustry', 'companyindustry', 'companyIndustry', $lv, $ds, 'setIndustry'),
            new FieldDefinition('companyindustry_linkedin', 'f4b_companyindustry_linkedin', 'companyIndustryLinkedin', $s, $auf),
            new FieldDefinition('companyindustry_list', 'f4b_companyindustry_list', 'companyIndustryList', $la, $auf),
            new FieldDefinition('companyindustry_naics', 'f4b_companyindustry_naicscode', 'companyIndustryNaicsCode', $s, $auf),
            new FieldDefinition('companyindustry_icb_industrygroup', 'f4b_companyindustry_icb_industrygroup', 'companyIndustryICBIndustryGroup', $lv, $auf),
            new FieldDefinition('companyindustry_icb_industry', 'f4b_companyindustry_icb_industry', 'companyIndustryICBCIndustry', $lv, $auf),
            new FieldDefinition('companyindustry_icb_subindustry', 'f4b_companyindustry_icb_subIndustry', 'companyIndustryICBCSubIndustry', $lv, $auf),
            new FieldDefinition('companyindustry_icb_sector', 'f4b_companyindustry_icb_sector', 'companyIndustryICBCSector', $lv, $auf),

            // --- Description fields ---
            new FieldDefinition('companydescription', 'f4b_companydescription', 'companyDescription', $s, $dau, 'setDescription'),
            new FieldDefinition('companydescription_long', 'f4b_companydescription_long', 'companyDescriptionLong', $s, $auf),

            // --- Company size and financials ---
            new FieldDefinition('companynumber_of_employees', 'companynumber_of_employees', 'companyNumberOfEmployees', $i, $auf),
            new FieldDefinition('companyemployees_range', 'f4b_companyemployees_range', 'companyEmployeeRange', $lv, $auf),
            new FieldDefinition('companymarketcap', 'f4b_companymarketcap', 'companyMarketCap', $s, $auf),
            new FieldDefinition('companyannual_revenue_range', 'f4b_companyannual_revenue_range', 'companyAnnualRevenueRange', $lv, $auf),
            new FieldDefinition('companyfunding_raised', 'f4b_companyfunding_raised', 'companyFundingRaised', $s, $auf),
            new FieldDefinition('companyrank_tranco', 'f4b_companyrank_tranco', 'companyRankTranco', $s, $auf),
            new FieldDefinition('companyfounding_year', 'f4b_companyfounding_year', 'companyFoundingYear', $s, $auf),

            // --- Labels and technologies ---
            new FieldDefinition('companylabels', 'f4b_companylabels', 'companyLabels', $la, $auf),
            new FieldDefinition('company_technologies', 'f4b_companytechnologies', 'companyTechnologies', $la, $auf),

            // --- Social media ---
            new FieldDefinition('companyfacebook_handle', 'f4b_companyfacebook_handle', 'companyFacebookHandle', $s, $auf),
            new FieldDefinition('companytwitter_handle', 'f4b_companytwitter_handle', 'companyTwitterHandle', $s, $auf),
            new FieldDefinition('companylinkedin_handle', 'f4b_companylinkedin_handle', 'companyLinkedinHandle', $s, $auf),
            new FieldDefinition('companytwitter_followers', 'f4b_companytwitter_followers', 'companyTwitterFollowers', $s, $auf),

            // --- Company metadata ---
            new FieldDefinition('companytype', 'f4b_companytype', 'companyType', $lv, $auf),
            new FieldDefinition('companyticker', 'f4b_companyticker', 'companyTicker', $s, $auf),
            new FieldDefinition('companytimezone', 'f4b_companytimezone', 'companyTimezone', $s, $auf),

            // --- Logo (not truncated, no Company mapping) ---
            new FieldDefinition('companylogo', 'f4b_companylogo', 'companyLogoUrl', $s, $no, null, false),
        ];
    }
}
