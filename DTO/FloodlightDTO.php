<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;

class FloodlightDTO implements DTOInterface
{
    public const DEFAULT_IP_HASH = 'local';

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

    private int $companyNumberOfEmployees;

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

    /**
     * BigPictureDTO constructor.
     *
     * @param array<string, mixed> $pureData
     */
    public function __construct(private array $pureData)
    {
        $this->load($pureData);
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

    public function getCompanyNumberOfEmployees(): int
    {
        return $this->companyNumberOfEmployees;
    }

    public function setCompanyNumberOfEmployees(int $companyNumberOfEmployees): void
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
     * @param array<mixed> $flCompanyData
     */
    public function load(array $flCompanyData): void
    {
        $this->setDomains([]);
        if (!empty($flCompanyData['primarydomain']) && !is_array($flCompanyData['primarydomain'])) {
            $this->setDomains([$flCompanyData['primarydomain']]);
        }

        $this->setCompanyName($flCompanyData['companyname'] ?? '');
        $this->setCompanyFloodlightId($flCompanyData['companyfloodlight_id'] ?? '');
        $this->setBigPictureId($flCompanyData['bigpicture_id'] ?? '');
        $this->setCompanyCrunchbaseHandle($flCompanyData['companycrunchbase_handle'] ?? '');
        $this->setCompanyPrimaryDomain($flCompanyData['primarydomain'] ?? '');
        $this->setCompanyNameLegal($flCompanyData['companyname_legal'] ?? '');
        $this->setCompanyNameAlternative($flCompanyData['companyname_alternatives'] ?? []);
        $this->setDomainAliases($flCompanyData['domainaliases'] ?? []);
        $this->setCompanyWebsite($flCompanyData['companywebsite'] ?? '');
        $this->setCompanyEmail($flCompanyData['companyemail'] ?? '');
        $this->setCompanyIndustry($flCompanyData['companyindustry'] ?? '');
        $this->setCompanyIndustryLinkedin($flCompanyData['companyindustry_linkedin'] ?? '');
        $this->setCompanyIndustryList($flCompanyData['companyindustry_list'] ?? []);
        $this->setCompanyLabels($flCompanyData['companylabels'] ?? []);
        $this->setCompanyIndustryNaicsCode($flCompanyData['companyindustry_naics'] ?? '');
        $this->setCompanyDescriptionLong($flCompanyData['companydescription_long'] ?? '');
        $this->setCompanyDescription($flCompanyData['companydescription'] ?? '');
        $this->setCompanyFoundingYear($flCompanyData['companyfounding_year'] ?? '');
        $this->setCompanyAddress1($flCompanyData['companyaddress1'] ?? '');
        $this->setCompanyAddress2($flCompanyData['companyaddress2'] ?? '');
        $this->setCompanyCity($flCompanyData['companycity'] ?? '');
        $this->setCompanyState($flCompanyData['companystate'] ?? '');
        $this->setCompanyCountry($flCompanyData['companycountry'] ?? '');
        $this->setCompanyCountryCode($flCompanyData['companycountry_code'] ?? '');
        $this->setCompanyZipCode($flCompanyData['companyzipcode'] ?? '');
        $this->setCompanyRankTranco($flCompanyData['companyrank_tranco'] ?? '');
        $this->setCompanyNumberOfEmployees(0);
        if (isset($flCompanyData['companynumber_of_employees']) && is_int($flCompanyData['companynumber_of_employees'])) {
            $this->setCompanyNumberOfEmployees($flCompanyData['companynumber_of_employees']);
        }
        $this->setCompanyEmployeeRange($flCompanyData['companyemployees_range'] ?? '');
        $this->setCompanyMarketCap($flCompanyData['companymarketcap'] ?? '');
        $this->setCompanyAnnualRevenueRange($flCompanyData['companyannual_revenue_range'] ?? '');
        $this->setCompanyFundingRaised($flCompanyData['companyfunding_raised'] ?? '');
        $this->setCompanyTechnologies($flCompanyData['company_technologies'] ?? []);
        $this->setCompanyFacebookHandle($flCompanyData['companyfacebook_handle'] ?? '');
        $this->setCompanyTwitterHandle($flCompanyData['companytwitter_handle'] ?? '');
        $this->setCompanyLinkedinHandle($flCompanyData['companylinkedin_handle'] ?? '');
        $this->setCompanyTwitterFollowers($flCompanyData['companytwitter_followers'] ?? '');
        $this->setCompanyLogoUrl($flCompanyData['companylogo'] ?? '');
        $this->setCompanyType($flCompanyData['companytype'] ?? '');
        $this->setCompanyTicker($flCompanyData['companyticker'] ?? '');
        $this->setCompanyPhone($flCompanyData['companyphone'] ?? '');
        $this->setCompanyTimezone($flCompanyData['companytimezone'] ?? '');
        $this->setIp($flCompanyData['ip'] ?? '');
        $this->setCompanyIndustryICBIndustryGroup($flCompanyData['companyindustry_icb_industrygroup'] ?? '');
        $this->setCompanyIndustryICBCIndustry($flCompanyData['companyindustry_icb_industry'] ?? '');
        $this->setCompanyIndustryICBCSubIndustry($flCompanyData['companyindustry_icb_subindustry'] ?? '');
        $this->setCompanyIndustryICBCSector($flCompanyData['companyindustry_icb_sector'] ?? '');
        $this->setCompanyStateCode($flCompanyData['companystate_code'] ?? '');
        $this->setIpHash($flCompanyData['ip_hash'] ?? '');
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

    public function fillCompanyDomain(FloodlightCompanyDomains $companyDomain): FloodlightCompanyDomains
    {
        $companyDomain->setDomain($this->getCompanyPrimaryDomain());
        $companyDomain->setCompany($companyDomain->getCompany());
        $companyDomain->setDateModified(new \DateTime());

        return $companyDomain;
    }

    public function fillCompany(Company $company): Company
    {
        $newFields = [];
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
            $newFields['companynumber_of_employees'] = $this->getCompanyNumberOfEmployees();
        }
        if (!empty($this->getCompanyFloodlightId())) {
            $newFields['f4b_companyfloodlight_id'] = $this->getCompanyFloodlightId();
            $company->addUpdatedField('f4b_companyfloodlight_id', $this->getCompanyFloodlightId());
        }
        if (!empty($this->getCompanyCrunchbaseHandle())) {
            $newFields['f4b_companycrunchbase_handle'] = $this->getCompanyCrunchbaseHandle();
            $company->addUpdatedField('f4b_companycrunchbase_handle', $this->getCompanyCrunchbaseHandle());
        }
        if (!empty($this->getCompanyNameLegal())) {
            $newFields['f4b_companyname_legal'] = $this->getCompanyNameLegal();
            $company->addUpdatedField('f4b_companyname_legal', $this->getCompanyNameLegal());
        }
        if (!empty($this->getCompanyNameAlternative())) {
            $newFields['f4b_companyname_alternatives'] = $this->getCompanyNameAlternative();
            $company->addUpdatedField('f4b_companyname_alternatives', $this->getCompanyNameAlternative());
        }
        if (!empty($this->getCompanyEmail())) {
            $company->setEmail($this->getCompanyEmail());
        }
        if (!empty($this->getCompanyIndustry())) {
            $company->setIndustry($this->getCompanyIndustry());
        }
        if (!empty($this->getCompanyIndustryLinkedin())) {
            $newFields['f4b_companyindustry_linkedin'] = $this->getCompanyIndustryLinkedin();
            $company->addUpdatedField('f4b_companyindustry_linkedin', $this->getCompanyIndustryLinkedin());
        }
        if (!empty($this->getCompanyIndustryList())) {
            $newFields['f4b_companyindustry_list'] = $this->getCompanyIndustryList();
            $company->addUpdatedField('f4b_companyindustry_list', $this->getCompanyIndustryList());
        }
        if (!empty($this->getCompanyLabels())) {
            $newFields['f4b_companylabels'] = $this->getCompanyLabels();
            $company->addUpdatedField('f4b_companylabels', $this->getCompanyLabels());
        }
        if (!empty($this->getCompanyIndustryNaicsCode())) {
            $newFields['f4b_companyindustry_naicscode'] = $this->getCompanyIndustryNaicsCode();
            $company->addUpdatedField('f4b_companyindustry_naicscode', $this->getCompanyIndustryNaicsCode());
        }
        if (!empty($this->getCompanyIndustryICBIndustryGroup())) {
            $newFields['f4b_companyindustry_icb_industrygroup'] = $this->getCompanyIndustryICBIndustryGroup();
            $company->addUpdatedField('f4b_companyindustry_icb_industrygroup', $this->getCompanyIndustryICBIndustryGroup());
        }
        if (!empty($this->getCompanyIndustryICBCIndustry())) {
            $newFields['f4b_companyindustry_icb_industry'] = $this->getCompanyIndustryICBCIndustry();
            $company->addUpdatedField('f4b_companyindustry_icb_industry', $this->getCompanyIndustryICBCIndustry());
        }
        if (!empty($this->getCompanyIndustryICBCSubIndustry())) {
            $newFields['f4b_companyindustry_icb_subIndustry'] = $this->getCompanyIndustryICBCSubIndustry();
            $company->addUpdatedField('f4b_companyindustry_icb_subIndustry', $this->getCompanyIndustryICBCSubIndustry());
        }
        if (!empty($this->getCompanyIndustryICBCSector())) {
            $newFields['f4b_companyindustry_icb_sector'] = $this->getCompanyIndustryICBCSector();
            $company->addUpdatedField('f4b_companyindustry_icb_sector', $this->getCompanyIndustryICBCSector());
        }
        if (!empty($this->getCompanyDescriptionLong())) {
            $newFields['f4b_companydescription_long'] = $this->getCompanyDescriptionLong();
            $company->addUpdatedField('f4b_companydescription_long', $this->getCompanyDescriptionLong());
        }
        if (!empty($this->getCompanyDescription())) {
            $newFields['f4b_companydescription'] = $this->getCompanyDescription();
            $company->addUpdatedField('f4b_companydescription', $this->getCompanyDescription());
        }
        if (!empty($this->getCompanyFoundingYear())) {
            $newFields['f4b_companyfounding_year'] = $this->getCompanyFoundingYear();
            $company->addUpdatedField('f4b_companyfounding_year', $this->getCompanyFoundingYear());
        }
        if (!empty($this->getCompanyState())) {
            $company->setState($this->getCompanyState());
        }
        if (!empty($this->getCompanyCountryCode())) {
            $newFields['f4b_companycountry_code'] = $this->getCompanyCountryCode();
            $company->addUpdatedField('f4b_companycountry_code', $this->getCompanyCountryCode());
        }
        if (!empty($this->getCompanyRankTranco())) {
            $newFields['f4b_companyrank_tranco'] = $this->getCompanyRankTranco();
            $company->addUpdatedField('f4b_companyrank_tranco', $this->getCompanyRankTranco());
        }
        if (!empty($this->getCompanyEmployeeRange())) {
            $newFields['f4b_companyemployees_range'] = $this->getCompanyEmployeeRange();
            $company->addUpdatedField('f4b_companyemployees_range', $this->getCompanyEmployeeRange());
        }
        if (!empty($this->getCompanyMarketCap())) {
            $newFields['f4b_companymarketcap'] = $this->getCompanyMarketCap();
            $company->addUpdatedField('f4b_companymarketcap', $this->getCompanyMarketCap());
        }
        if (!empty($this->getCompanyAnnualRevenueRange())) {
            $newFields['f4b_companyannual_revenue_range'] = $this->getCompanyAnnualRevenueRange();
            $company->addUpdatedField('f4b_companyannual_revenue_range', $this->getCompanyAnnualRevenueRange());
        }
        if (!empty($this->getCompanyFundingRaised())) {
            $newFields['f4b_companyfunding_raised'] = $this->getCompanyFundingRaised();
            $company->addUpdatedField('f4b_companyfunding_raised', $this->getCompanyFundingRaised());
        }
        if (!empty($this->getCompanyTechnologies())) {
            $newFields['f4b_companytechnologies'] = $this->getCompanyTechnologies();
            $company->addUpdatedField('f4b_companytechnologies', $this->getCompanyTechnologies());
        }
        if (!empty($this->getCompanyFacebookHandle())) {
            $newFields['f4b_companyfacebook_handle'] = $this->getCompanyFacebookHandle();
            $company->addUpdatedField('f4b_companyfacebook_handle', $this->getCompanyFacebookHandle());
        }
        if (!empty($this->getCompanyTwitterHandle())) {
            $newFields['f4b_companytwitter_handle'] = $this->getCompanyTwitterHandle();
            $company->addUpdatedField('f4b_companytwitter_handle', $this->getCompanyTwitterHandle());
        }
        if (!empty($this->getCompanyLinkedinHandle())) {
            $newFields['f4b_companylinkedin_handle'] = $this->getCompanyLinkedinHandle();
            $company->addUpdatedField('f4b_companylinkedin_handle', $this->getCompanyLinkedinHandle());
        }
        if (!empty($this->getCompanyTwitterFollowers())) {
            $newFields['f4b_companytwitter_followers'] = $this->getCompanyTwitterFollowers();
            $company->addUpdatedField('f4b_companytwitter_followers', $this->getCompanyTwitterFollowers());
        }
        if (!empty($this->getCompanyLogoUrl())) {
            $newFields['f4b_companylogo'] = $this->getCompanyLogoUrl();
            $company->addUpdatedField('f4b_companylogo', $this->getCompanyLogoUrl());
        }
        if (!empty($this->getCompanyType())) {
            $newFields['f4b_companytype'] = $this->getCompanyType();
            $company->addUpdatedField('f4b_companytype', $this->getCompanyType());
        }
        if (!empty($this->getCompanyTicker())) {
            $newFields['f4b_companyticker'] = $this->getCompanyTicker();
            $company->addUpdatedField('f4b_companyticker', $this->getCompanyTicker());
        }
        if (!empty($this->getCompanyPhone())) {
            $company->setPhone($this->getCompanyPhone());
        }
        if (!empty($this->getCompanyTimezone())) {
            $newFields['f4b_companytimezone'] = $this->getCompanyTimezone();
            $company->addUpdatedField('f4b_companytimezone', $this->getCompanyTimezone());
        }
        if (!empty($this->getBigPictureId())) {
            $newFields['f4b_bigpicture_id'] = $this->getBigPictureId();
            $company->addUpdatedField('f4b_bigpicture_id', $this->getBigPictureId());
        }
        if (!empty($this->getCompanyStateCode())) {
            $newFields['f4b_companystate_code'] = $this->getCompanyStateCode();
            $company->addUpdatedField('f4b_companystate_code', $this->getCompanyStateCode());
        }

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
            $domain        = $this->cleanDomain($domain);
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
            $companyDomain->setEnrichmentDate(new \DateTime());
            $result[] = $companyDomain;
        }

        return $result;
    }

    private function cleanDomain(?string $domain): ?string
    {
        if (is_null($domain)) {
            return null;
        }
        $parts = explode('.', $domain);
        reset($parts);
        $firstPart = current($parts);
        preg_match_all('/\d/', $firstPart, $matches);
        reset($parts);
        if (count($matches[0]) > 1) {
            return implode('.', array_slice($parts, 1));
        }

        return $domain;
    }

    public function fillLeadDomainLookUp(): FloodlightLeadDomains
    {
        $ipLeadDomainLookups = new FloodlightLeadDomains();
        $domain              = $this->getCompanyPrimaryDomain();
        if (empty($domain) && !empty($this->getDomains())) {
            $domain = $this->getDomains()[0];
        }
        $ipLeadDomainLookups->setDomain($domain);
        $ipLeadDomainLookups->setType($this->getCompanyType());
        $ipLeadDomainLookups->setDateAdded(new \DateTime());
        $ipLeadDomainLookups->setEnrichmentDate(new \DateTime());
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
}
