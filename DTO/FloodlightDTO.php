<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightIpDomainLookups;
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

    /**
     * BigPictureDTO constructor.
     *
     * @param array<string, mixed> $bigpictureData
     */
    public function __construct(array $bigpictureData)
    {
        $this->load($bigpictureData);
    }

    public function load(array $data): void
    {
        // TODO: Implement load() method.
    }

    public function fillCompany(Company $company): Company
    {
        // TODO: Implement fillCompany() method.
    }

    public function fillCompaniesDomain(FloodlightCompanyDomainsModel $floodlightCompanyDomainsModel, Company $company): Company
    {
        // TODO: Implement fillCompaniesDomain() method.
    }

    public function fillIpLookUp(): FloodlightIpDomainLookups
    {
        $ipDomainLookups = new FloodlightIpDomainLookups();
        $ipDomainLookups->setDomain($this->getDomain());
        $ipDomainLookups->setIp($this->getIp());
        $ipDomainLookups->setType($this->getType());
        $ipDomainLookups->setDateAdded(new \DateTime());

        return $ipDomainLookups;
    }
}
