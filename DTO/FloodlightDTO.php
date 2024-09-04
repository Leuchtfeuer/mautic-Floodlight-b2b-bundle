<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;

class FloodlightDTO implements DTOInterface
{
    private string $name;
    private string $country;
    private string $city;
    private string $zipCode;
    private string $addressLine1;
    private string $website;
    /** @var array<string> */
    private array $tags = [];
    private string $annualRevenue;
    private int $numberOfEmployees;
    private string $description;
    /** @var array<string> */
    private array $domains;

    private string $ip;

    private string $type;

    /**
     * BigPictureDTO constructor.
     *
     * @param array<string, mixed> $bigpictureData
     */
    public function __construct(array $data)
    {
        $this->load($data['content']);
    }

    public function load(array $data): void
    {
        $flCompanyData                 = $data['company'];
        $this->domains[]               = $flCompanyData['primary_domain'];
        $this->name                    = $flCompanyData['name'] ?? '';
        $this->country                 = $flCompanyData['country'] ?? '';
        $this->city                    = $flCompanyData['city'] ?? '';
        $this->zipCode                 = $flCompanyData['zip'] ?? '';
        $this->addressLine1            = '';
        $streetName                    = $flCompanyData['address1'] ?? '';
        $streetNumber                  = $flCompanyData['address2'] ?? '';
        if (!empty($streetName) && !empty($streetNumber)) {
            $this->addressLine1 = $streetName.' '.$streetNumber;
        }
        $this->website               = $flCompanyData['url'] ?? '';
        $this->tags                  = $flCompanyData['industry_tags'] ?? [];
        $this->annualRevenue         = $flCompanyData['annual_revenue'] ?? '';
        $this->numberOfEmployees     = $flCompanyData['number_of_employees'] ?? 0;
        $this->description           = $flCompanyData['description'] ?? '';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getZipCode(): string
    {
        return $this->zipCode;
    }

    public function setZipCode(string $zipCode): void
    {
        $this->zipCode = $zipCode;
    }

    public function getAddressLine1(): string
    {
        return $this->addressLine1;
    }

    public function setAddressLine1(string $addressLine1): void
    {
        $this->addressLine1 = $addressLine1;
    }

    public function getWebsite(): string
    {
        return $this->website;
    }

    public function setWebsite(string $website): void
    {
        $this->website = $website;
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

    public function getAnnualRevenue(): string
    {
        return $this->annualRevenue;
    }

    public function setAnnualRevenue(string $annualRevenue): void
    {
        $this->annualRevenue = $annualRevenue;
    }

    public function getNumberOfEmployees(): int
    {
        return $this->numberOfEmployees;
    }

    public function setNumberOfEmployees(int $numberOfEmployees): void
    {
        $this->numberOfEmployees = $numberOfEmployees;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
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
        if (!empty($this->getName())) {
            $company->setName($this->getName());
        }
        if (!empty($this->getCity())) {
            $company->setCity($this->getCity());
        }
        if (!empty($this->getCountry())) {
            $company->setCountry($this->getCountry());
        }
        if (!empty($this->getAddressLine1())) {
            $company->setAddress1($this->getAddressLine1());
        }
        if (!empty($this->getWebsite())) {
            $company->setWebsite($this->getWebsite());
        }
        if (!empty($this->getZipCode())) {
            $company->setZipcode($this->getZipCode());
        }
        if (!empty($this->getDescription())) {
            $company->setDescription($this->getDescription());
        }
        if (!empty($this->getAnnualRevenue())) {
            $company->addUpdatedField('companyannual_revenue', $this->getAnnualRevenue());
        }
        if (!empty($this->getNumberOfEmployees())) {
            $company->addUpdatedField('companynumber_of_employees', $this->getNumberOfEmployees());
        }
        if (!empty($this->getTags())) {
            $company->addUpdatedField('tags', json_encode($this->getTags(), JSON_FORCE_OBJECT));
        }
        $company->addUpdatedField('enrichment_date', \date('Y-m-d H:i:s'));

        return $company;
    }

    /**
     * @return array<FloodlightCompanyDomains>|array
     */
    public function fillCompaniesDomain(FloodlightCompanyDomainsModel $companyDomainsModel, Company $company): array
    {
        if (empty($this->getDomains())) {
            return [];
        }
        $result = [];
        foreach ($this->getDomains() as $domain) {
            $companyDomain = $companyDomainsModel->getRepository()->findOneBy(
                [
                    'domain'  => $domain,
                    'company' => $company,
                ]
            );
            if (!empty($companyDomain)) {
                continue;
            }
            $companyDomain = $companyDomainsModel->getEntity();
            $companyDomain->setDomain($domain);
            $companyDomain->setCompany($company);
            $companyDomain->setIsPrimaryDomain(false);
            $companyDomain->setDateAdded(new \DateTime());
            $companyDomain->setDateModified(new \DateTime());
            $result[] = $companyDomain;
        }

        return $result;
    }
}
