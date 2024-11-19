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
        private FloodlightLeadDomainsModel $leadDomainsModel,
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
    public function save(string $domain, Lead $lead, array $details): array
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
        $this->leadModel->addToCompany($lead, $companyDomain->getCompany());
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
    private function newCompanyDomainByFloodlight(FloodlightDTO $floodlightDTO, Lead $lead): array
    {
        // Save IP lookup
        $ipDomainLookup = $floodlightDTO->fillIpLookUp();
        $this->leadDomainsModel->saveEntity($ipDomainLookup);
        $this->logger->error('fillIpLookUp data for IP: '.$ipDomainLookup->getIp().' was successfully retrieved with id'.$ipDomainLookup->getId());

        // Update fields values properties from FloodlightDTO
        $this->fieldHelper->updateProperties($floodlightDTO);

        $this->logger->error('Updating fields values properties for IP: ');

        // Save company
        $company       = $floodlightDTO->fillCompany($this->companiesModel->getEntity());
        $this->logger->error('Company data for IP: '.$company->getName().' was successfully retrieved with id '.$company->getId());
        $this->companiesModel->saveEntity($company);

        // Add company to lead and set as primary company
        $this->leadModel->addToCompany($lead, $company);
        $lead->setPrimaryCompany($company);
        $this->leadModel->saveEntity($lead);

        // Save company domain
        $companiesDomains = $floodlightDTO->fillCompaniesDomain($this, $company);
        if (!empty($companiesDomains)) {
            foreach ($companiesDomains as $companiesDomain) {
                $this->logger->error('Company domain data for IP: '.$company->getName().' was successfully retrieved with id '.$companiesDomain->getId());
            }
            $this->saveEntities($companiesDomains);
        }

        return $companiesDomains;
    }

    /**
     * @param array<string,array|FloodlightDTO> $details
     *
     * @return array<FloodlightCompanyDomains>
     */
    private function newCompanyDomain(string $domain, Lead $lead, array $details = []): array
    {
        if (!empty($details) && array_key_exists('floodlight', $details)) {
            return $this->newCompanyDomainByFloodlight($details['floodlight'], $lead);
        }

        $this->newCompany($domain, $lead, $details);
        $companies     = $this->leadModel->getCompanies($lead);
        $company       = $this->companiesModel->getEntity($companies[0]['company_id']);

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
     *
     * @return array<string, Company|Lead>
     */
    private function newCompany(string $domain, Lead $lead, array $details): array
    {
        $company       = $this->companiesModel->getEntity();
        $userIds       = $this->notificationUserHelper->getAdminUsers();
        $user          = $this->userModel->getEntity($userIds[0]);

        $company->setName($domain);
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());
        $company->setIsPublished(true);
        $company->setCreatedBy($user);
        $company->setModifiedBy($user);
        $company->setOwner($user);
        if (!empty($details) && isset($details['pyservice'])) {
            $company->addUpdatedField('iplookup_whois_json', json_encode($this->transformDetails($details['pyservice']), JSON_THROW_ON_ERROR));
        }
        $this->companiesModel->saveEntity($company);
        $this->leadModel->addToCompany($lead, $company);
        $lead->setPrimaryCompany($company);
        $this->leadModel->saveEntity($lead);

        return [
            'company' => $company,
            'lead'    => $lead,
        ];
    }

    /**
     * @param array<> $domainDetails
     */
    public function updateOrCreateCompanyAndCompanyDomainAndAddOrCountLeadDomain(int $lead, array $dataDomain): FloodlightLeadDomains
    {
        $leadEntity    = $this->leadModel->getEntity($lead);
        $companyDomain = $this->getRepository()->findOneBy(
            [
                'domain' => $dataDomain['domain'],
            ]
        );
        if (
            empty($companyDomain)
            && IpLookupRulesHelper::LEAD_DOMAINS_STATUS_FAILURE !== $dataDomain['status']
            && IpLookupRulesHelper::LEAD_DOMAINS_STATUS_BLACKLISTED !== $dataDomain['status']
            && null !== $leadEntity
        ) {
            $companyDomain = $this->save($dataDomain['domain'], $leadEntity, $dataDomain['details']);
        }

        $leadDomain = $this->leadDomainsModel->getRepository()->findOneBy(
            [
                'lead'   => $lead,
                'domain' => $dataDomain['domain'],
            ]
        );

        if (empty($leadDomain) && null !== $leadEntity) {
            $leadDomain = $this->leadDomainsModel->getEntity();
            $leadDomain->setLead($leadEntity);
            $leadDomain->setDomain($dataDomain['domain']);
            $leadDomain->setDateAdded(new \DateTime());
            $leadDomain->setCount(0);
        }

        assert($leadDomain instanceof FloodlightLeadDomains);

        $leadDomain->setDateModified(new \DateTime());
        $leadDomain->setCount($leadDomain->getCount() + 1);

        $this->em->persist($leadDomain);
        $this->em->flush();

        return $leadDomain;
    }
}
