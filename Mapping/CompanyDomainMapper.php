<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Mapping;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;

class CompanyDomainMapper
{
    public function __construct(
        private readonly DomainUtility $domainUtility, private readonly \MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomainsRepository $floodlightCompanyDomainsRepository,
    ) {
    }

    /**
     * @return array<FloodlightCompanyDomains>
     */
    public function mapToCompanyDomains(
        FloodlightDTO $dto,
        FloodlightCompanyDomainsModel $floodlightCompanyDomainsModel,
        Company $company,
    ): array {
        if (empty($dto->getDomains())) {
            return [];
        }

        $result = [];

        foreach ($dto->getDomains() as $domain) {
            $domain = $this->domainUtility->cleanDomain($domain);

            if (empty($domain)) {
                continue;
            }

            $companyDomain = $this->floodlightCompanyDomainsRepository->findOneBy(
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
            if ($domain === $dto->getCompanyPrimaryDomain()) {
                $companyDomain->setIsPrimaryDomain(true);
            }

            $companyDomain->setDateAdded(new \DateTime());
            $companyDomain->setDateModified(new \DateTime());

            $result[] = $companyDomain;
        }

        return $result;
    }

    public function mapToLeadDomainLookup(FloodlightDTO $dto): FloodlightLeadDomains
    {
        $ipLeadDomainLookups = new FloodlightLeadDomains();
        $domain              = $dto->getCompanyPrimaryDomain();
        if (empty($domain) && !empty($dto->getDomains())) {
            $domain = $dto->getDomains()[0];
        }
        $ipLeadDomainLookups->setDomain($domain);
        $ipLeadDomainLookups->setLookupType($dto->getCompanyType());
        $ipLeadDomainLookups->setDateAdded(new \DateTime());
        $ipLeadDomainLookups->setCount(1);
        $ipHash = $dto->getIpHash();
        if (empty($ipHash)) {
            $ipHash = FloodlightDTO::DEFAULT_IP_HASH;
        }
        $ipLeadDomainLookups->setIpHash($ipHash);

        return $ipLeadDomainLookups;
    }
}
