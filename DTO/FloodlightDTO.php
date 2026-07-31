<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

class FloodlightDTO implements DTOInterface
{
    public const DEFAULT_IP_HASH = 'local';

    /** @var array<string> */
    private array $companyNameAlternative = [];

    /** @var array<string> */
    private array $domainAliases = [];

    private string $companyWebsite = '';

    private string $companyEmail = '';

    private string $companyIndustry = '';

    private string $companyIndustryLinkedin = '';

    /** @var array<string> */
    private array $companyIndustryList = [];

    /** @var array<string> */
    private array $companyLabels = [];

    private string $companyIndustryNaicsCode = '';

    private string $companyIndustryICBIndustryGroup = '';

    private string $ip = '';

    private string $companyIndustryICBCIndustry = '';

    private string $companyIndustryICBCSubIndustry = '';

    private string $companyIndustryICBCSector = '';

    private string $companyDescriptionLong = '';

    private string $companyDescription = '';

    private string $companyFoundingYear = '';

    private string $companyAddress1 = '';

    private string $companyAddress2 = '';

    private string $companyCity = '';

    private string $companyState = '';

    private string $companyCountry = '';

    private string $companyCountryCode = '';

    private string $companyZipCode = '';

    private string $companyRankTranco = '';

    private ?int $companyNumberOfEmployees = null;

    private string $companyEmployeeRange = '';

    private string $companyMarketCap = '';

    private string $companyAnnualRevenueRange = '';

    private string $companyFundingRaised = '';

    /** @var array<string> */
    private array $companyTechnologies = [];

    private string $companyFacebookHandle = '';

    private string $companyTwitterHandle = '';

    private string $companyLinkedinHandle = '';

    private string $companyTwitterFollowers = '';

    private string $companyLogoUrl = '';

    private string $companyType = '';

    private string $companyTicker = '';

    private string $companyPhone = '';

    private string $companyTimezone = '';

    /** @var array<string> */
    private array $tags = [];

    /** @var array<string> */
    private array $domains = [];

    private string $companyName = '';

    private string $companyFloodlightId = '';

    private string $bigPictureId = '';

    private string $companyCrunchbaseHandle = '';

    private string $companyPrimaryDomain = '';

    private string $companyNameLegal = '';

    private string $companyStateCode = '';

    private string $ipHash = '';

    private ?int $statusHttpRequest = null;

    /**
     * @param array<string, mixed> $pureData
     */
    public function __construct(
        private array $pureData,
    ) {
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
        $this->companyDescription = $companyDescription;
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
}
