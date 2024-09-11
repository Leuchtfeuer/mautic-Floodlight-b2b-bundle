<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;

interface DTOInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function load(array $data): void;

    public function fillCompany(Company $company): Company;

    /**
     * @return array<FloodlightCompanyDomains>
     */
    public function fillCompaniesDomain(FloodlightCompanyDomainsModel $floodlightCompanyDomainsModel, Company $company): array;
}
