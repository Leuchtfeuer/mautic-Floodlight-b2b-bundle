<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FloodlightCompanyDomainsModel extends FormModel
{
    public function __construct(
        EntityManagerInterface $em,
        CorePermissions $security,
        EventDispatcherInterface $dispatcher,
        UrlGeneratorInterface $router,
        Translator $translator,
        UserHelper $userHelper,
        LoggerInterface $logger,
        CoreParametersHelper $coreParametersHelper,
        private DomainUtility $domainUtility,
        private CompanyModel $companyModel,
    ) {
        parent::__construct($em, $security, $dispatcher, $router, $translator, $userHelper, $logger, $coreParametersHelper);
    }

    public function getRepository()
    {
        return $this->em->getRepository(FloodlightCompanyDomains::class);
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
        try {
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
        } catch (\Exception $e) {
            throw $e;
        }
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

    public function getCompanyDomainsEntriesOfCompany(int $companyId): array
    {
        $companyDomains = $this->getRepository()->findBy(
            [
                'company' => $companyId,
            ]
        );

        return $companyDomains;
    }

    public function createNewCompanyDomain(FloodlightDTO $floodlightDTO, Company $company): array
    {
        $companiesDomains = $floodlightDTO->fillCompaniesDomain($this, $company);

        if (!empty($companiesDomains)) {
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
