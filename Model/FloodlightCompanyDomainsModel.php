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
        private CompanyModel $companiesModel,
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
            dump(['Error' => $e->getMessage()]);
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

        $companies = $this->companiesModel->getEntities(['ids' => $companyIds]);

        foreach ($companies as $company) {
            $domain = $this->extractDomainFromCompanywebsite($company->getCompanywebsite());

            if (!$domain || !empty($this->getExistingCompanyDomain($domain))) {
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

    private function extractDomainFromCompanywebsite(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $parsed = parse_url($url);
        $host   = $parsed['host'] ?? '';

        return empty($host) ? null : preg_replace('/^www\./', '', $host);
    }

    /**
     * @return mixed[]
     */
    public function getExistingCompanyDomain(string $domain): array
    {
        return $this->getRepository()->findBy(
            [
                'domain' => $domain,
            ]
        );
    }

    public function createNewCompanyDomain(FloodlightDTO $floodlightDTO, Company $company): FloodlightCompanyDomains
    {
        // Save company domain
        $companiesDomains = $floodlightDTO->fillCompaniesDomain($this, $company);

        if (!empty($companiesDomains)) {
            foreach ($companiesDomains as $companiesDomain) {
                $companiesDomain->setDomain($this->cleanDomain($companiesDomain->getDomain()));
            }
            $this->saveEntities($companiesDomains);
        }

        return $companiesDomains[0];
    }
}
