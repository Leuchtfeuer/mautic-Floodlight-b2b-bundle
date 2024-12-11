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

        return parent::getEntity($id);
    }

    /**
     * @param array<string,array|FloodlightDTO|string> $details
     *
     * @return array<FloodlightCompanyDomains>
     */
    public function save(string $domain, ?Lead $lead, array $details): array
    {
        $companyDomain = $this->getRepository()->findOneBy(
            [
                'domain' => $domain,
            ]
        );

        if (empty($companyDomain)) {
            return $this->newCompanyDomain($domain, $lead, $details);
        }

        if (!empty($details) && isset($details['pyservice'])) {
            $this->updateFieldWhoisDetails($companyDomain, $details);
        }

        if (null !== $lead) {
            $this->leadModel->addToCompany($lead, $companyDomain->getCompany());
        }
        $companyDomain->setIsPrimaryDomain(true);

        $this->saveEntity($companyDomain);

        return [$companyDomain];
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

    /**
     * @return array<FloodlightCompanyDomains>
     */
    private function newCompanyDomainByFloodlight(array $floodlightData, ?Lead $lead): array
    {
        assert($floodlightData['floodlight'] instanceof FloodlightDTO);
        $floodlightDTO = $floodlightData['floodlight'];

        // Update fields values properties from FloodlightDTO
        $this->fieldHelper->updateProperties($floodlightDTO);

        $company = $this->companiesModel->getEntity();
        $company       = $floodlightDTO->fillCompany($company);
        $this->companiesModel->saveEntity($company);

        $this->logger->info('Company data for IP: '.$company->getName().' was successfully retrieved with id '.$company->getId());


        if (null !== $lead) {
            $this->leadModel->addToCompany($lead, $company);
        }

        // Save company domain
        $companiesDomains = $floodlightDTO->fillCompaniesDomain($this, $company);

        if (!empty($companiesDomains)) {
            $this->saveEntities($companiesDomains);
            foreach ($companiesDomains as $companiesDomain) {
                $companiesDomain->setDomain($this->cleanDomain($companiesDomain->getDomain()));
                $this->logger->info('Company domain data for IP: '.$company->getName().' was successfully retrieved with domain ');
            }
            $this->saveEntities($companiesDomains);
        }

        return $companiesDomains;
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

    public function recalculatePrimaryDomainDomain(Company $company): void
    {
        $companyDomains = $this->getRepository()->findBy(
            [
                'company' => $company,
            ]
        );

        $primaryCompanyDomain = null;
        $count                = 0;
        foreach ($companyDomains as $companyDomain) {
            if (null === $primaryCompanyDomain && 0 === $count) {
                $primaryCompanyDomain = $companyDomain;
                $count                = $companyDomain->getCount();
            }
            if ($companyDomain->getCount() > $count) {
                $primaryCompanyDomain = $companyDomain;
                $count                = $companyDomain->getCount();
            }
        }

        if (null !== $primaryCompanyDomain) {
            $primaryCompanyDomain->setIsPrimaryDomain(true);
            $this->saveEntity($primaryCompanyDomain);
        }
    }

    /**
     * @param array<string,array|FloodlightDTO> $details
     *
     * @return array<FloodlightCompanyDomains>
     */
    private function newCompanyDomain(?string $domain, ?Lead $lead, array $details = []): array
    {
        if (!empty($details) && array_key_exists('floodlight', $details)) {
            // Create test to result 202
            //{
            //     "ip_hash": "f7bb85bc2a65e38d5106cb1cb9c430852035a544"
            //}
            return $this->newCompanyDomainByFloodlight($details, $lead);
        }

        // Create test to this option
        if (null === $domain) {
            return [];
        }

        $company = $this->newCompanyByGetHostByAddrOrPythonWhois($domain, $lead, $details);

        $companyDomain = new FloodlightCompanyDomains();
        $companyDomain->setDomain($domain);
        $companyDomain->setDateAdded(new \DateTime());
        $companyDomain->setDateModified(new \DateTime());
        $companyDomain->setIsPrimaryDomain(true);
        if (!empty($company->getId())) {
            $companyDomain->setCompany($company);
        }

        $this->saveEntity($companyDomain);

        return [$companyDomain];
    }

    /**
     * @param array<string,string|array> $details
     */
    public function newCompanyByGetHostByAddrOrPythonWhois(string $domain, ?Lead $lead, array $details): ?Company
    {

        $userIds       = $this->notificationUserHelper->getAdminUsers();
        $user          = $this->userModel->getEntity($userIds[0]);

        $company       = $this->companiesModel->getEntity();
        $company->setName($domain);
        $company->setDateAdded(new \DateTime());
        $company->setIsPublished(true);
        $company->setCreatedBy($user);
        $company->setModifiedBy($user);
        $company->setOwner($user);

        if (!empty($details) && isset($details['pyservice'])) {
            $company->addUpdatedField('iplookup_whois_json', json_encode($this->transformDetails($details['pyservice']), JSON_THROW_ON_ERROR));
        }

        $this->companiesModel->saveEntity($company);

        if (null !== $lead) {
            $this->leadModel->addToCompany($lead, $company);
            $lead->setPrimaryCompany($company);
            $this->leadModel->saveEntity($lead);
        }

        return $company;
    }

    public function getCompanyDomains(?Lead $lead, array $dataDomain): array|false
    {
        if (null === $lead ) {
            return false;
        }
        $companyDomain = null;
        if ($dataDomain['domain'] !== null) {
            $companyDomain = $this->getRepository()->findBy(
                [
                    'domain' => $dataDomain['domain'],
                ]
            );
        }


        if (!empty($companyDomain)) {
            return [
                'isNew' => false,
                'data' => $companyDomain,
            ];
        }

        if (
            IpLookupRulesHelper::LEAD_DOMAINS_STATUS_FAILURE !== $dataDomain['status']
            && IpLookupRulesHelper::LEAD_DOMAINS_STATUS_BLACKLISTED !== $dataDomain['status']
        ) {
            $companyDomain = $this->newCompanyDomain($dataDomain['domain'], $lead, $dataDomain['details']);
            return [
                'isNew' => true,
                'data' => $companyDomain,
            ];
        }

        return false;
    }

    /**
     * @param array<FloodlightCompanyDomains> $companyDomains
     */
    public function updateCompanyDomains(array $companyDomains, array $dataDomain): void
    {
        foreach ($companyDomains as $companyDomain) {
            $companyDomain->setDateModified(new \DateTime());
            $companyDomain->setCount($companyDomain->getCount() + 1);
            $this->saveEntity($companyDomain);
        }
    }

    public function getCompanyDomainToEnrichment()
    {
        $qb = $this->getRepository()->createQueryBuilder('cd');
        $qb->select('cd')
            ->where('enrichmentDate IS NULL')
            ->orWhere('enrichmentDate = :enrichmentDate')
            ->setParameter('enrichmentDate', '1970-01-01 00:00:00');

        return $qb->getQuery()->getResult();
    }
}
