<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\IntegrationsBundle\Sync\Notification\Helper\UserHelper as NotificationUserHelper;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper;
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
        private LeadModel $leadModel,
        private NotificationUserHelper $notificationUserHelper,
        private UserModel $userModel,
        private FieldHelper $fieldHelper,
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
     * @param array<string> $details
     */
    private function updateFieldWhoisDetails(FloodlightCompanyDomains $companyDomain, array $details): void
    {
        $companyDomain->getCompany()->addUpdatedField(
            'iplookup_whois_json',
            json_encode($this->transformDetails($details), JSON_FORCE_OBJECT)
        );
        $this->companiesModel->saveEntity($companyDomain->getCompany());
    }

    /**
     * @param array<string> $details
     *
     * @return array<string>
     */
    private function transformDetails(array $details): array
    {
        return [
            'whois_company_name' => $details['company_name'],
            'whois_is_isp'       => $details['is_isp'],
            'whois_is_dialup'    => $details['is_dialup'],
        ];
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
        // Verwende eine einfachere Zwei-Schritt-Methode
        try {
            // Schritt 1: Finde alle Company-IDs mit nativer SQL-Abfrage
            $connection = $this->em->getConnection();
            $query = $connection->createQueryBuilder()
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
