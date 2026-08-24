<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model;

use Mautic\CoreBundle\Model\FormModel;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomainsRepository;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Mapping\CompanyDomainMapper;
use Symfony\Contracts\Service\Attribute\Required;

class FloodlightCompanyDomainsModel extends FormModel
{
    private DomainUtility $domainUtility;

    private CompanyModel $companyModel;

    private CompanyDomainMapper $companyDomainMapper;

    private FloodlightCompanyDomainsRepository $floodlightCompanyDomainsRepository;

    #[Required]
    public function autowireFloodlightCompanyDomainsModel(
        DomainUtility $domainUtility,
        CompanyModel $companyModel,
        CompanyDomainMapper $companyDomainMapper,
        FloodlightCompanyDomainsRepository $floodlightCompanyDomainsRepository,
    ): void {
        $this->domainUtility                      = $domainUtility;
        $this->companyModel                       = $companyModel;
        $this->companyDomainMapper                = $companyDomainMapper;
        $this->floodlightCompanyDomainsRepository = $floodlightCompanyDomainsRepository;
    }

    public function getRepository(): FloodlightCompanyDomainsRepository
    {
        return $this->floodlightCompanyDomainsRepository;
    }

    /**
     * @param int|null $id
     */
    public function getEntity($id = null): FloodlightCompanyDomains
    {
        if (null === $id) {
            return new FloodlightCompanyDomains();
        }

        $entity = parent::getEntity($id);

        if (null === $entity) {
            return new FloodlightCompanyDomains();
        }
        assert($entity instanceof FloodlightCompanyDomains);

        return $entity;
    }

    /**
     * @return array<FloodlightCompanyDomains>|mixed|null
     */
    public function getCompanyDomainToEnrichment()
    {
        $connection = $this->em->getConnection();
        $query      = $connection->createQueryBuilder()
            ->select('id')
            ->from(MAUTIC_TABLE_PREFIX.'companies', 'c')
            ->where('c.f4b_enrichment_date IS NULL OR c.f4b_enrichment_date = :enrichmentDate')
            ->setParameter('enrichmentDate', '1970-01-01 00:00:00');
        $companyIds = $query->executeQuery()->fetchFirstColumn();
        if (empty($companyIds)) {
            return [];
        }

        // Schritt 2: Nutze die Doctrine-Repository-Methode mit IN-Abfrage
        return $this->getRepository()->createQueryBuilder('cd')
            ->where('cd.company IN (:companyIds)')
            ->andWhere('cd.domain IS NOT NULL')
            ->setParameter('companyIds', $companyIds)
            ->getQuery()
            ->getResult();
    }

    public function saveDomainsForNonFloodlightCompanies(): void
    {
        $connection = $this->em->getConnection();
        $query      = $connection->createQueryBuilder()
            ->select('c.id')
            ->from(MAUTIC_TABLE_PREFIX.'companies', 'c')
            ->where('c.f4b_companyfloodlight_id IS NULL')
            ->andWhere('c.companywebsite IS NOT NULL')
            ->andWhere('c.companywebsite != :empty')
            ->setParameter('empty', '');

        $companyIds = $query->executeQuery()->fetchFirstColumn();

        if (empty($companyIds)) {
            return;
        }

        $companies = $this->companyModel->getEntities(['ids' => $companyIds]);

        foreach ($companies as $company) {
            $domain = $this->domainUtility->cleanDomain($company->getCompanywebsite());

            if (!$domain || ($this->getExistingCompanyDomain($domain) instanceof FloodlightCompanyDomains)) {
                continue;
            }

            $companyDomain = $this->getEntity();
            $companyDomain->setCompany($company);
            $companyDomain->setDomain($domain);
            $companyDomain->setDateAdded(new \DateTime());
            $companyDomain->setDateModified(new \DateTime());
            $companyDomain->setIsPrimaryDomain(false);

            $this->saveEntity($companyDomain);
        }
    }

    public function getExistingCompanyDomain(string $domain): ?FloodlightCompanyDomains
    {
        $domain = $this->getRepository()->findOneBy(
            [
                'domain' => $domain,
            ]
        );
        if ($domain instanceof FloodlightCompanyDomains) {
            return $domain;
        }

        return null;
    }

    /**
     * @return array<FloodlightCompanyDomains>
     */
    public function getCompanyDomainsEntriesOfCompany(int $companyId): array
    {
        /** @var array<FloodlightCompanyDomains> $companyDomains */
        $companyDomains = $this->getRepository()->findBy(
            [
                'company' => $companyId,
            ]
        );

        return $companyDomains;
    }

    /**
     * @return array<FloodlightCompanyDomains>
     */
    public function createNewCompanyDomain(FloodlightDTO $floodlightDTO, Company $company): array
    {
        $companiesDomains = $this->companyDomainMapper->mapToCompanyDomains($floodlightDTO, $this, $company);

        if ([] !== $companiesDomains) {
            foreach ($companiesDomains as $companiesDomain) {
                $domain = $this->domainUtility->cleanDomain($companiesDomain->getDomain());
                if (null === $domain) {
                    continue;
                }
                $companiesDomain->setDomain($domain);
            }
            $this->saveEntities($companiesDomains);

            return $companiesDomains;
        }

        return [];
    }
}
