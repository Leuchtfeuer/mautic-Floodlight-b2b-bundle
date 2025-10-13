<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;

class FloodlightDTO implements DTOInterface
{
    public const DEFAULT_IP_HASH = 'local';

    private const FIELD_MAPPING = [
        'company_technologies'              => 'f4b_companytechnologies',
        'companylabels'                     => 'f4b_companylabels',
        'companyindustry_linkedin'          => 'f4b_companyindustry_linkedin',
        'companyindustry_list'              => 'f4b_companyindustry_list',
        'companydescription'                => 'f4b_companydescription',
        'companydescription_long'           => 'f4b_companydescription_long',
        'companyname_legal'                 => 'f4b_companyname_legal',
        'companyname_alternatives'          => 'f4b_companyname_alternatives',
        'companyfloodlight_id'              => 'f4b_companyfloodlight_id',
        'companycrunchbase_handle'          => 'f4b_companycrunchbase_handle',
        'companyindustry_naics'             => 'f4b_companyindustry_naicscode',
        'companyindustry_icb_industrygroup' => 'f4b_companyindustry_icb_industrygroup',
        'companyindustry_icb_industry'      => 'f4b_companyindustry_icb_industry',
        'companyindustry_icb_subindustry'   => 'f4b_companyindustry_icb_subIndustry',
        'companyindustry_icb_sector'        => 'f4b_companyindustry_icb_sector',
        'companyfounding_year'              => 'f4b_companyfounding_year',
        'companycountry_code'               => 'f4b_companycountry_code',
        'companyrank_tranco'                => 'f4b_companyrank_tranco',
        'companyemployees_range'            => 'f4b_companyemployees_range',
        'companymarketcap'                  => 'f4b_companymarketcap',
        'companyannual_revenue_range'       => 'f4b_companyannual_revenue_range',
        'companyfunding_raised'             => 'f4b_companyfunding_raised',
        'companyfacebook_handle'            => 'f4b_companyfacebook_handle',
        'companytwitter_handle'             => 'f4b_companytwitter_handle',
        'companylinkedin_handle'            => 'f4b_companylinkedin_handle',
        'companytwitter_followers'          => 'f4b_companytwitter_followers',
        'companytype'                       => 'f4b_companytype',
        'companyticker'                     => 'f4b_companyticker',
        'companytimezone'                   => 'f4b_companytimezone',
        'bigpicture_id'                     => 'f4b_bigpicture_id',
        'companystate_code'                 => 'f4b_companystate_code',

        'companyname'                        => 'companyname',
        'companycity'                        => 'companycity',
        'companystate'                       => 'companystate',
        'companycountry'                     => 'companycountry',
        'companywebsite'                     => 'companywebsite',
        'companyemail'                       => 'companyemail',
        'companyindustry'                    => 'companyindustry',
        'companyaddress1'                    => 'companyaddress1',
        'companyaddress2'                    => 'companyaddress2',
        'companyzipcode'                     => 'companyzipcode',
        'companyphone'                       => 'companyphone',
        'companynumber_of_employees'         => 'companynumber_of_employees',
    ];

    /** @var array<string> */
    private array $companyNameAlternative;

    /** @var array<string> */
    private array $domainAliases;

    private string $companyWebsite;

    private string $companyEmail;

    private string $companyIndustry;

    private string $companyIndustryLinkedin;

    /** @var array<string> */
    private array $companyIndustryList;

    /** @var array<string> */
    private array $companyLabels;

    private string $companyIndustryNaicsCode;

    private string $companyIndustryICBIndustryGroup;

    private string $ip;

    private string $companyIndustryICBCIndustry;

    private string $companyIndustryICBCSubIndustry;

    private string $companyIndustryICBCSector;

    private string $companyDescriptionLong;

    private string $companyDescription;

    private string $companyFoundingYear;

    private string $companyAddress1;

    private string $companyAddress2;

    private string $companyCity;

    private string $companyState;

    private string $companyCountry;

    private string $companyCountryCode;

    private string $companyZipCode;

    private string $companyRankTranco;

    private ?int $companyNumberOfEmployees = null;

    private string $companyEmployeeRange;

    private string $companyMarketCap;

    private string $companyAnnualRevenueRange;

    private string $companyFundingRaised;

    /** @var array<string> */
    private array $companyTechnologies;

    private string $companyFacebookHandle;

    private string $companyTwitterHandle;

    private string $companyLinkedinHandle;

    private string $companyTwitterFollowers;

    private string $companyLogoUrl;

    private string $companyType;

    private string $companyTicker;

    private string $companyPhone;

    private string $companyTimezone;

    /** @var array<string> */
    private array $tags = [];

    /** @var array<string> */
    private array $domains;

    private string $companyName;

    private string $companyFloodlightId;

    private string $bigPictureId;

    private string $companyCrunchbaseHandle;

    private string $companyPrimaryDomain;

    private string $companyNameLegal;

    private string $companyStateCode;

    private string $ipHash;

    private ?int $statusHttpRequest = null;

    /**
     * @param array<string, mixed> $pureData
     */
    public function __construct(
        private array $pureData,
        private FieldModel $fieldModel,
        private DomainUtility $domainUtility,
    ) {
        $this->load($pureData);
    }

    public function getStatusHttpRequest(): ?int
    {
        return $this->statusHttpRequest;
    }

    public function setStatusHttpRequest(?int $statusHttpRequest): void
    {
        $this->statusHttpRequest = $statusHttpRequest;
    }

    public function getCompanyStateCode(): string
    {
        return $this->companyStateCode;
    }

    public function setCompanyStateCode(string $companyStateCode): void
    {
        $this->companyStateCode = $companyStateCode;
    }

    /** @return array<string, mixed> */
    public function getPureData(): array
    {
        return $this->pureData;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): void
    {
        $this->ip = $ip;
    }

    public function setCompanyIndustryICBIndustryGroup(string $companyIndustryICBIndustryGroup): void
    {
        $this->companyIndustryICBIndustryGroup = $companyIndustryICBIndustryGroup;
    }

    public function getCompanyIndustryICBIndustryGroup(): string
    {
        return $this->companyIndustryICBIndustryGroup;
    }

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): void
    {
        $this->companyName = $companyName;
    }

    public function getCompanyFloodlightId(): string
    {
        return $this->companyFloodlightId;
    }

    public function setCompanyFloodlightId(string $companyFloodlightId): void
    {
        $this->companyFloodlightId = $companyFloodlightId;
    }

    public function getBigPictureId(): string
    {
        return $this->bigPictureId;
    }

    public function setBigPictureId(string $bigPictureId): void
    {
        $this->bigPictureId = $bigPictureId;
    }

    public function getCompanyCrunchbaseHandle(): string
    {
        return $this->companyCrunchbaseHandle;
    }

    public function setCompanyCrunchbaseHandle(string $companyCrunchbaseHandle): void
    {
        $this->companyCrunchbaseHandle = $companyCrunchbaseHandle;
    }

    public function getCompanyPrimaryDomain(): string
    {
        return $this->companyPrimaryDomain;
    }

    public function setCompanyPrimaryDomain(string $companyPrimaryDomain): void
    {
        $this->companyPrimaryDomain = $companyPrimaryDomain;
    }

    public function getCompanyNameLegal(): string
    {
        return $this->companyNameLegal;
    }

    public function setCompanyNameLegal(string $companyNameLegal): void
    {
        $this->companyNameLegal = $companyNameLegal;
    }

    /**
     * @return array<string>
     */
    public function getCompanyNameAlternative(): array
    {
        return $this->companyNameAlternative;
    }

    /**
     * @param array<string> $companyNameAlternative
     */
    public function setCompanyNameAlternative(array $companyNameAlternative): void
    {
        $this->companyNameAlternative = $companyNameAlternative;
    }

    /**
     * @return array<string>
     */
    public function getDomainAliases(): array
    {
        return $this->domainAliases;
    }

    /**
     * @param array<string> $domainAliases
     */
    public function setDomainAliases(array $domainAliases): void
    {
        $this->domainAliases = $domainAliases;
    }

    public function getCompanyWebsite(): string
    {
        return $this->companyWebsite;
    }

    public function setCompanyWebsite(string $companyWebsite): void
    {
        $this->companyWebsite = $companyWebsite;
    }

    public function getCompanyEmail(): string
    {
        return $this->companyEmail;
    }

    public function setCompanyEmail(string $companyEmail): void
    {
        $this->companyEmail = $companyEmail;
    }

    public function getCompanyIndustry(): string
    {
        return $this->companyIndustry;
    }

    public function setCompanyIndustry(string $companyIndustry): void
    {
        $this->companyIndustry = $companyIndustry;
    }

    public function getCompanyIndustryLinkedin(): string
    {
        return $this->companyIndustryLinkedin;
    }

    public function setCompanyIndustryLinkedin(string $companyIndustryLinkedin): void
    {
        $this->companyIndustryLinkedin = $companyIndustryLinkedin;
    }

    /**
     * @return array<string>
     */
    public function getCompanyIndustryList(): array
    {
        return $this->companyIndustryList;
    }

    /**
     * @param array<string> $companyIndustryList
     */
    public function setCompanyIndustryList(array $companyIndustryList): void
    {
        $this->companyIndustryList = $companyIndustryList;
    }

    /**
     * @return array<string>
     */
    public function getCompanyLabels(): array
    {
        return $this->companyLabels;
    }

    /**
     * @param array<string> $companyLabels
     */
    public function setCompanyLabels(array $companyLabels): void
    {
        $this->companyLabels = $companyLabels;
    }

    public function getCompanyIndustryNaicsCode(): string
    {
        return $this->companyIndustryNaicsCode;
    }

    public function setCompanyIndustryNaicsCode(string $companyIndustryNaicsCode): void
    {
        $this->companyIndustryNaicsCode = $companyIndustryNaicsCode;
    }

    public function getCompanyIndustryICBCIndustry(): string
    {
        return $this->companyIndustryICBCIndustry;
    }

    public function setCompanyIndustryICBCIndustry(string $companyIndustryICBCIndustry): void
    {
        $this->companyIndustryICBCIndustry = $companyIndustryICBCIndustry;
    }

    public function getCompanyIndustryICBCSubIndustry(): string
    {
        return $this->companyIndustryICBCSubIndustry;
    }

    public function setCompanyIndustryICBCSubIndustry(string $companyIndustryICBCSubIndustry): void
    {
        $this->companyIndustryICBCSubIndustry = $companyIndustryICBCSubIndustry;
    }

    public function getCompanyIndustryICBCSector(): string
    {
        return $this->companyIndustryICBCSector;
    }

    public function setCompanyIndustryICBCSector(string $companyIndustryICBCSector): void
    {
        $this->companyIndustryICBCSector = $companyIndustryICBCSector;
    }

    public function getCompanyDescriptionLong(): string
    {
        return $this->companyDescriptionLong;
    }

    public function setCompanyDescriptionLong(string $companyDescriptionLong): void
    {
        $this->companyDescriptionLong = $companyDescriptionLong;
    }

    public function getCompanyDescription(): string
    {
        return $this->companyDescription;
    }

    public function setCompanyDescription(string $companyDescription): void
    {
        $this->companyDescription = substr($companyDescription, 0, 190);
    }

    public function getCompanyFoundingYear(): string
    {
        return $this->companyFoundingYear;
    }

    public function setCompanyFoundingYear(string $companyFoundingYear): void
    {
        $this->companyFoundingYear = $companyFoundingYear;
    }

    public function getCompanyAddress1(): string
    {
        return $this->companyAddress1;
    }

    public function setCompanyAddress1(string $companyAddress1): void
    {
        $this->companyAddress1 = $companyAddress1;
    }

    public function getCompanyAddress2(): string
    {
        return $this->companyAddress2;
    }

    public function setCompanyAddress2(string $companyAddress2): void
    {
        $this->companyAddress2 = $companyAddress2;
    }

    public function getCompanyCity(): string
    {
        return $this->companyCity;
    }

    public function setCompanyCity(string $companyCity): void
    {
        $this->companyCity = $companyCity;
    }

    public function getCompanyState(): string
    {
        return $this->companyState;
    }

    public function setCompanyState(string $companyState): void
    {
        $this->companyState = $companyState;
    }

    public function getCompanyCountry(): string
    {
        return $this->companyCountry;
    }

    public function setCompanyCountry(string $companyCountry): void
    {
        $this->companyCountry = $companyCountry;
    }

    public function getCompanyCountryCode(): string
    {
        return $this->companyCountryCode;
    }

    public function setCompanyCountryCode(string $companyCountryCode): void
    {
        $this->companyCountryCode = $companyCountryCode;
    }

    public function getCompanyZipCode(): string
    {
        return $this->companyZipCode;
    }

    public function setCompanyZipCode(string $companyZipCode): void
    {
        $this->companyZipCode = $companyZipCode;
    }

    public function getCompanyRankTranco(): string
    {
        return $this->companyRankTranco;
    }

    public function setCompanyRankTranco(string $companyRankTranco): void
    {
        $this->companyRankTranco = $companyRankTranco;
    }

    public function getCompanyNumberOfEmployees(): ?int
    {
        return $this->companyNumberOfEmployees;
    }

    public function setCompanyNumberOfEmployees(?int $companyNumberOfEmployees): void
    {
        $this->companyNumberOfEmployees = $companyNumberOfEmployees;
    }

    public function getCompanyEmployeeRange(): string
    {
        return $this->companyEmployeeRange;
    }

    public function setCompanyEmployeeRange(string $companyEmployeeRange): void
    {
        $this->companyEmployeeRange = $companyEmployeeRange;
    }

    public function getCompanyMarketCap(): string
    {
        return $this->companyMarketCap;
    }

    public function setCompanyMarketCap(string $companyMarketCap): void
    {
        $this->companyMarketCap = $companyMarketCap;
    }

    public function getCompanyAnnualRevenueRange(): string
    {
        return $this->companyAnnualRevenueRange;
    }

    public function setCompanyAnnualRevenueRange(string $companyAnnualRevenueRange): void
    {
        $this->companyAnnualRevenueRange = $companyAnnualRevenueRange;
    }

    public function getCompanyFundingRaised(): string
    {
        return $this->companyFundingRaised;
    }

    public function setCompanyFundingRaised(string $companyFundingRaised): void
    {
        $this->companyFundingRaised = $companyFundingRaised;
    }

    /**
     * @return array<string>
     */
    public function getCompanyTechnologies(): array
    {
        return $this->companyTechnologies;
    }

    /**
     * @param array<string> $companyTechnologies
     */
    public function setCompanyTechnologies(array $companyTechnologies): void
    {
        $this->companyTechnologies = $companyTechnologies;
    }

    public function getCompanyFacebookHandle(): string
    {
        return $this->companyFacebookHandle;
    }

    public function setCompanyFacebookHandle(string $companyFacebookHandle): void
    {
        $this->companyFacebookHandle = $companyFacebookHandle;
    }

    public function getCompanyTwitterHandle(): string
    {
        return $this->companyTwitterHandle;
    }

    public function setCompanyTwitterHandle(string $companyTwitterHandle): void
    {
        $this->companyTwitterHandle = $companyTwitterHandle;
    }

    public function getCompanyLinkedinHandle(): string
    {
        return $this->companyLinkedinHandle;
    }

    public function setCompanyLinkedinHandle(string $companyLinkedinHandle): void
    {
        $this->companyLinkedinHandle = $companyLinkedinHandle;
    }

    public function getCompanyTwitterFollowers(): string
    {
        return $this->companyTwitterFollowers;
    }

    public function setCompanyTwitterFollowers(string $companyTwitterFollowers): void
    {
        $this->companyTwitterFollowers = $companyTwitterFollowers;
    }

    public function getCompanyLogoUrl(): string
    {
        return $this->companyLogoUrl;
    }

    public function setCompanyLogoUrl(string $companyLogoUrl): void
    {
        $this->companyLogoUrl = $companyLogoUrl;
    }

    public function getCompanyType(): string
    {
        return $this->companyType;
    }

    public function setCompanyType(string $companyType): void
    {
        $this->companyType = $companyType;
    }

    public function getCompanyTicker(): string
    {
        return $this->companyTicker;
    }

    public function setCompanyTicker(string $companyTicker): void
    {
        $this->companyTicker = $companyTicker;
    }

    public function getCompanyPhone(): string
    {
        return $this->companyPhone;
    }

    public function setCompanyPhone(string $companyPhone): void
    {
        $this->companyPhone = $companyPhone;
    }

    public function getCompanyTimezone(): string
    {
        return $this->companyTimezone;
    }

    public function setCompanyTimezone(string $companyTimezone): void
    {
        $this->companyTimezone = $companyTimezone;
    }

    /**
     * @param array<string|int|mixed> $value
     */
    private function getIntToLoad(array $value, string $key): ?int
    {
        if (!isset($value[$key])) {
            return null;
        }

        if (is_numeric($value[$key])) {
            $value = (int) $value[$key];

            return $this->truncateInt($value, $key);
        }

        return null;
    }

    /**
     * @param array<string|int|mixed> $value
     */
    private function getStringToLoad(array $value, string $key): string
    {
        if (!isset($value[$key])) {
            return '';
        }

        if (is_string($value[$key])) {
            $value[$key] = $this->truncateString($value[$key], $key);
        }

        if (is_numeric($value[$key])) {
            $value[$key] = $this->truncateString((string) $value[$key], $key);
        }

        if (!is_string($value[$key])) {
            return '';
        }

        return $value[$key];
    }

    /**
     * @param array<string|int|mixed> $value
     *
     * @return array<string>
     */
    private function getArrayToLoad(array $value, string $key): array
    {
        if (!isset($value[$key])) {
            return [];
        }

        if (is_string($value[$key]) || is_numeric($value[$key])) {
            $stringvalue = $this->truncateString((string) $value[$key], $key);

            return [$stringvalue];
        }

        if (is_array($value[$key])) {
            return $this->truncateArray($value[$key], $key);
        }

        return [];
    }

    /**
     * @param array<string,mixed> $flCompanyData
     */
    public function load(array $flCompanyData): void
    {
        $this->setDomains($this->getArrayToLoad($flCompanyData, 'primarydomain'));
        //        if (!empty($flCompanyData['primarydomain']) && !is_array($flCompanyData['primarydomain']) && is_string($flCompanyData['primarydomain'])) {
        //            $this->setDomains([$flCompanyData['primarydomain']]);
        //        }
        $this->setStatusHttpRequest($this->getIntToLoad($flCompanyData, 'statusRequestCode'));
        $this->setCompanyName($this->getStringToLoad($flCompanyData, 'companyname'));
        $this->setCompanyFloodlightId($this->getStringToLoad($flCompanyData, 'companyfloodlight_id'));
        $this->setBigPictureId($this->getStringToLoad($flCompanyData, 'bigpicture_id'));
        $this->setCompanyCrunchbaseHandle($this->getStringToLoad($flCompanyData, 'companycrunchbase_handle'));
        $this->setCompanyPrimaryDomain($this->getStringToLoad($flCompanyData, 'primarydomain'));
        $this->setCompanyNameLegal($this->getStringToLoad($flCompanyData, 'companyname_legal'));
        $this->setCompanyNameAlternative($this->getArrayToLoad($flCompanyData, 'companyname_alternatives'));
        $this->setDomainAliases($this->getArrayToLoad($flCompanyData, 'domainaliases'));
        $this->setCompanyWebsite($this->getStringToLoad($flCompanyData, 'companywebsite'));
        $this->setCompanyEmail($this->getStringToLoad($flCompanyData, 'companyemail'));
        $this->setCompanyIndustry($this->getStringToLoad($flCompanyData, 'companyindustry'));
        $this->setCompanyIndustryLinkedin($this->getStringToLoad($flCompanyData, 'companyindustry_linkedin'));
        $this->setCompanyIndustryList($this->getArrayToLoad($flCompanyData, 'companyindustry_list'));
        $this->setCompanyLabels($this->getArrayToLoad($flCompanyData, 'companylabels'));
        $this->setCompanyIndustryNaicsCode($this->getStringToLoad($flCompanyData, 'companyindustry_naics'));
        $this->setCompanyDescriptionLong($this->getStringToLoad($flCompanyData, 'companydescription_long'));
        $this->setCompanyDescription($this->getStringToLoad($flCompanyData, 'companydescription'));
        $this->setCompanyFoundingYear($this->getStringToLoad($flCompanyData, 'companyfounding_year'));
        $this->setCompanyAddress1($this->getStringToLoad($flCompanyData, 'companyaddress1'));
        $this->setCompanyAddress2($this->getStringToLoad($flCompanyData, 'companyaddress2'));
        $this->setCompanyCity($this->getStringToLoad($flCompanyData, 'companycity'));
        $this->setCompanyState($this->getStringToLoad($flCompanyData, 'companystate'));
        $this->setCompanyCountry($this->getStringToLoad($flCompanyData, 'companycountry'));
        $this->setCompanyCountryCode($this->getStringToLoad($flCompanyData, 'companycountry_code'));
        $this->setCompanyZipCode($this->getStringToLoad($flCompanyData, 'companyzipcode'));
        $this->setCompanyRankTranco($this->getStringToLoad($flCompanyData, 'companyrank_tranco'));
        $this->setCompanyNumberOfEmployees($this->getIntToLoad($flCompanyData, 'companynumber_of_employees'));
        $this->setCompanyEmployeeRange($this->getStringToLoad($flCompanyData, 'companyemployees_range'));
        $this->setCompanyMarketCap($this->getStringToLoad($flCompanyData, 'companymarketcap'));
        $this->setCompanyAnnualRevenueRange($this->getStringToLoad($flCompanyData, 'companyannual_revenue_range'));
        $this->setCompanyFundingRaised($this->getStringToLoad($flCompanyData, 'companyfunding_raised'));
        $this->setCompanyTechnologies($this->getArrayToLoad($flCompanyData, 'company_technologies'));
        $this->setCompanyFacebookHandle($this->getStringToLoad($flCompanyData, 'companyfacebook_handle'));
        $this->setCompanyTwitterHandle($this->getStringToLoad($flCompanyData, 'companytwitter_handle'));
        $this->setCompanyLinkedinHandle($this->getStringToLoad($flCompanyData, 'companylinkedin_handle'));
        $this->setCompanyTwitterFollowers($this->getStringToLoad($flCompanyData, 'companytwitter_followers'));
        $this->setCompanyLogoUrl($this->getStringToLoad($flCompanyData, 'companylogo'));
        $this->setCompanyType($this->getStringToLoad($flCompanyData, 'companytype'));
        $this->setCompanyTicker($this->getStringToLoad($flCompanyData, 'companyticker'));
        $this->setCompanyPhone($this->getStringToLoad($flCompanyData, 'companyphone'));
        $this->setCompanyTimezone($this->getStringToLoad($flCompanyData, 'companytimezone'));
        $this->setIp($this->getStringToLoad($flCompanyData, 'ip'));
        $this->setCompanyIndustryICBIndustryGroup($this->getStringToLoad($flCompanyData, 'companyindustry_icb_industrygroup'));
        $this->setCompanyIndustryICBCIndustry($this->getStringToLoad($flCompanyData, 'companyindustry_icb_industry'));
        $this->setCompanyIndustryICBCSubIndustry($this->getStringToLoad($flCompanyData, 'companyindustry_icb_subindustry'));
        $this->setCompanyIndustryICBCSector($this->getStringToLoad($flCompanyData, 'companyindustry_icb_sector'));
        $this->setCompanyStateCode($this->getStringToLoad($flCompanyData, 'companystate_code'));
        $this->setIpHash($this->getStringToLoad($flCompanyData, 'ip_hash'));
        if (!empty($this->getDomainAliases())) {
            $domains = $this->getDomains();
            foreach ($this->getDomainAliases() as $domain) {
                $domains[] = $domain;
            }
            $this->setDomains($domains);
        }
    }

    public function getIpHash(): string
    {
        return $this->ipHash;
    }

    public function setIpHash(string $ipHash): void
    {
        $this->ipHash = $ipHash;
    }

    /** @return array<string> */
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @param array<string> $tags */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function addTag(string $tag): void
    {
        $this->tags[] = $tag;
    }

    /** @return array<string> */
    public function getDomains(): array
    {
        return $this->domains;
    }

    /** @param array<string> $domains */
    public function setDomains(array $domains): void
    {
        $this->domains = $domains;
    }

    public function fillCompany(Company $company): Company
    {
        if (!empty($this->getCompanyName())) {
            $company->setName($this->getCompanyName());
        }
        if (!empty($this->getCompanyCity())) {
            $company->setCity($this->getCompanyCity());
        }
        if (!empty($this->getCompanyCountry())) {
            $company->setCountry($this->getCompanyCountry());
        }

        if (!empty($this->getCompanyAddress1())) {
            $company->setAddress1($this->getCompanyAddress1());
        }
        if (!empty($this->getCompanyAddress2())) {
            $company->setAddress2($this->getCompanyAddress2());
        }
        if (!empty($this->getCompanyWebsite())) {
            $company->setWebsite($this->getCompanyWebsite());
        }
        if (!empty($this->getCompanyZipCode())) {
            $company->setZipcode($this->getCompanyZipCode());
        }
        if (!empty($this->getCompanyDescription())) {
            $company->setDescription($this->getCompanyDescription());
        }
        if (!empty($this->getCompanyNumberOfEmployees())) {
            $company->addUpdatedField('companynumber_of_employees', $this->getCompanyNumberOfEmployees());
        }
        if (!empty($this->getCompanyFloodlightId())) {
            $company->addUpdatedField('f4b_companyfloodlight_id', $this->getCompanyFloodlightId());
        }
        if (!empty($this->getCompanyCrunchbaseHandle())) {
            $company->addUpdatedField('f4b_companycrunchbase_handle', $this->getCompanyCrunchbaseHandle());
        }
        if (!empty($this->getCompanyNameLegal())) {
            $company->addUpdatedField('f4b_companyname_legal', $this->getCompanyNameLegal());
        }
        if (!empty($this->getCompanyNameAlternative())) {
            $company->addUpdatedField('f4b_companyname_alternatives', $this->getCompanyNameAlternative());
        }
        if (!empty($this->getCompanyEmail())) {
            $company->setEmail($this->getCompanyEmail());
        }
        if (!empty($this->getCompanyIndustry())) {
            $company->setIndustry($this->getCompanyIndustry());
        }
        if (!empty($this->getCompanyIndustryLinkedin())) {
            $company->addUpdatedField('f4b_companyindustry_linkedin', $this->getCompanyIndustryLinkedin());
        }
        if (!empty($this->getCompanyIndustryList())) {
            $company->addUpdatedField('f4b_companyindustry_list', $this->getCompanyIndustryList());
        }
        if (!empty($this->getCompanyLabels())) {
            $company->addUpdatedField('f4b_companylabels', $this->getCompanyLabels());
        }
        if (!empty($this->getCompanyIndustryNaicsCode())) {
            $company->addUpdatedField('f4b_companyindustry_naicscode', $this->getCompanyIndustryNaicsCode());
        }
        if (!empty($this->getCompanyIndustryICBIndustryGroup())) {
            $company->addUpdatedField('f4b_companyindustry_icb_industrygroup', $this->getCompanyIndustryICBIndustryGroup());
        }
        if (!empty($this->getCompanyIndustryICBCIndustry())) {
            $company->addUpdatedField('f4b_companyindustry_icb_industry', $this->getCompanyIndustryICBCIndustry());
        }
        if (!empty($this->getCompanyIndustryICBCSubIndustry())) {
            $company->addUpdatedField('f4b_companyindustry_icb_subIndustry', $this->getCompanyIndustryICBCSubIndustry());
        }
        if (!empty($this->getCompanyIndustryICBCSector())) {
            $company->addUpdatedField('f4b_companyindustry_icb_sector', $this->getCompanyIndustryICBCSector());
        }
        if (!empty($this->getCompanyDescriptionLong())) {
            $company->addUpdatedField('f4b_companydescription_long', $this->getCompanyDescriptionLong());
        }
        if (!empty($this->getCompanyDescription())) {
            $company->addUpdatedField('f4b_companydescription', $this->getCompanyDescription());
        }
        if (!empty($this->getCompanyFoundingYear())) {
            $company->addUpdatedField('f4b_companyfounding_year', $this->getCompanyFoundingYear());
        }
        if (!empty($this->getCompanyState())) {
            $company->setState($this->getCompanyState());
        }
        if (!empty($this->getCompanyCountryCode())) {
            $company->addUpdatedField('f4b_companycountry_code', $this->getCompanyCountryCode());
        }
        if (!empty($this->getCompanyRankTranco())) {
            $company->addUpdatedField('f4b_companyrank_tranco', $this->getCompanyRankTranco());
        }
        if (!empty($this->getCompanyEmployeeRange())) {
            $company->addUpdatedField('f4b_companyemployees_range', $this->getCompanyEmployeeRange());
        }
        if (!empty($this->getCompanyMarketCap())) {
            $company->addUpdatedField('f4b_companymarketcap', $this->getCompanyMarketCap());
        }
        if (!empty($this->getCompanyAnnualRevenueRange())) {
            $company->addUpdatedField('f4b_companyannual_revenue_range', $this->getCompanyAnnualRevenueRange());
        }
        if (!empty($this->getCompanyFundingRaised())) {
            $company->addUpdatedField('f4b_companyfunding_raised', $this->getCompanyFundingRaised());
        }
        if (!empty($this->getCompanyTechnologies())) {
            $company->addUpdatedField('f4b_companytechnologies', $this->getCompanyTechnologies());
        }
        if (!empty($this->getCompanyFacebookHandle())) {
            $company->addUpdatedField('f4b_companyfacebook_handle', $this->getCompanyFacebookHandle());
        }
        if (!empty($this->getCompanyTwitterHandle())) {
            $company->addUpdatedField('f4b_companytwitter_handle', $this->getCompanyTwitterHandle());
        }
        if (!empty($this->getCompanyLinkedinHandle())) {
            $company->addUpdatedField('f4b_companylinkedin_handle', $this->getCompanyLinkedinHandle());
        }
        if (!empty($this->getCompanyTwitterFollowers())) {
            $company->addUpdatedField('f4b_companytwitter_followers', $this->getCompanyTwitterFollowers());
        }
        if (!empty($this->getCompanyType())) {
            $company->addUpdatedField('f4b_companytype', $this->getCompanyType());
        }
        if (!empty($this->getCompanyTicker())) {
            $company->addUpdatedField('f4b_companyticker', $this->getCompanyTicker());
        }
        if (!empty($this->getCompanyPhone())) {
            $company->setPhone($this->getCompanyPhone());
        }
        if (!empty($this->getCompanyTimezone())) {
            $company->addUpdatedField('f4b_companytimezone', $this->getCompanyTimezone());
        }
        if (!empty($this->getBigPictureId())) {
            $company->addUpdatedField('f4b_bigpicture_id', $this->getBigPictureId());
        }
        if (!empty($this->getCompanyStateCode())) {
            $company->addUpdatedField('f4b_companystate_code', $this->getCompanyStateCode());
        }
        if (200 === $this->getStatusHttpRequest()) {
            $company->addUpdatedField('f4b_enrichment_date', (new \DateTime())->format('Y-m-d H:i:s'));
        }

        $company->setEventData('floodlightAuditLogMarker', true);

        return $company;
    }

    /**
     * @return array<FloodlightCompanyDomains>
     */
    public function fillCompaniesDomain(FloodlightCompanyDomainsModel $floodlightCompanyDomainsModel, Company $company): array
    {
        if (empty($this->getDomains())) {
            return [];
        }
        $result = [];

        foreach ($this->getDomains() as $domain) {
            $domain        = $this->domainUtility->cleanDomain($domain);

            if (empty($domain)) {
                continue;
            }

            $companyDomain = $floodlightCompanyDomainsModel->getRepository()->findOneBy(
                [
                    'domain'  => $domain,
                    'company' => $company,
                ]
            );

            if (!empty($companyDomain)) {
                continue;
            }
            $companyDomain = $floodlightCompanyDomainsModel->getEntity();
            $companyDomain->setDomain($domain);
            $companyDomain->setCompany($company);
            $companyDomain->setIsPrimaryDomain(false);
            if ($domain === $this->getCompanyPrimaryDomain()) {
                $companyDomain->setIsPrimaryDomain(true);
            }

            $companyDomain->setDateAdded(new \DateTime());
            $companyDomain->setDateModified(new \DateTime());

            $result[] = $companyDomain;
        }

        return $result;
    }

    public function fillLeadDomainLookUp(): FloodlightLeadDomains
    {
        $ipLeadDomainLookups = new FloodlightLeadDomains();
        $domain              = $this->getCompanyPrimaryDomain();
        if (empty($domain) && !empty($this->getDomains())) {
            $domain = $this->getDomains()[0];
        }
        $ipLeadDomainLookups->setDomain($domain);
        $ipLeadDomainLookups->setLookupType($this->getCompanyType());
        $ipLeadDomainLookups->setDateAdded(new \DateTime());
        $ipLeadDomainLookups->setCount(1);
        $ipHash = $this->getIpHash();
        if (empty($ipHash)) {
            $ipHash = self::DEFAULT_IP_HASH;
        }
        $ipLeadDomainLookups->setIpHash($ipHash);

        return $ipLeadDomainLookups;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @return mixed
     */
    public function getDataByJsonKey(string $key)
    {
        return $this->pureData[$key] ?? '';
    }

    private function truncateString(string $value, string $dtoFieldName): string
    {
        if (!array_key_exists($dtoFieldName, self::FIELD_MAPPING)) {
            return $value;
        }

        $limit = $this->getFieldLimit($dtoFieldName);

        if (null === $limit || strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit);
    }

    /**
     * @param array<string> $values
     *
     * @return array<string>
     */
    private function truncateArray(array $values, string $fieldAlias): array
    {
        if (empty($values)) {
            return $values;
        }

        if (!array_key_exists($fieldAlias, self::FIELD_MAPPING)) {
            return $values;
        }

        $limit = $this->getFieldLimit($fieldAlias);

        if (null === $limit) {
            return $values;
        }

        $result        = [];
        $pipeSeparated = '';

        foreach ($values as $value) {
            $testString = $pipeSeparated.($pipeSeparated ? '|' : '').$value;

            if (strlen($testString) <= $limit) {
                $pipeSeparated = $testString;
                $result[]      = $value;
            } else {
                break;
            }
        }

        return $result;
    }

    private function truncateInt(int $value, string $dtoFieldName): int
    {
        if (!array_key_exists($dtoFieldName, self::FIELD_MAPPING)) {
            return $value;
        }

        $limit = $this->getFieldLimit($dtoFieldName);

        if (null === $limit || strlen((string) $value) <= $limit) {
            return $value;
        }

        return (int) substr((string) $value, 0, $limit);
    }

    private function getFieldLimit(string $dtoFieldName): ?int
    {
        $dbFieldName = self::FIELD_MAPPING[$dtoFieldName];

        $field = $this->fieldModel->getEntityByAlias($dbFieldName);

        return $field && method_exists($field, 'getCharLengthLimit') ? $field->getCharLengthLimit() : null;
    }
}
