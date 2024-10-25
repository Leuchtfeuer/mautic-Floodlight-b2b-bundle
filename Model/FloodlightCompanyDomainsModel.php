<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\IntegrationsBundle\Sync\Notification\Helper\UserHelper as NotificationUserHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener\PluginInstallSubscriber;
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
        private FloodlightLeadDomainsModel $leadDomainsModel
    ) {
        parent::__construct($em, $security, $dispatcher, $router, $translator, $userHelper, $logger, $coreParametersHelper);
    }

    public function getRepository()
    {
        return $this->em->getRepository(FloodlightCompanyDomains::class);
    }

    public function getEntity($id = null): ?object
    {
        if (null === $id) {
            return new FloodlightCompanyDomains();
        }

        return parent::getEntity($id);
    }

    public function save(string $domain, Lead $lead, array $details): FloodlightCompanyDomains
    {
        $companyDomain = $this->getRepository()->findOneBy(
            [
                'domain' => $domain,
            ]
        );
        if (empty($companyDomain)) {
            return $this->newCompanyDomain($domain, $lead, $details);
        }

        if (!empty($details) && isset($details['company_name'])) {
            $this->updateFieldWhoisDetails($companyDomain, $details);
        }
        $this->leadModel->addToCompany($lead, $companyDomain->getCompany());
        $companyDomain->setIsPrimaryDomain(true);

        $this->saveEntity($companyDomain);

        return $companyDomain;
    }

    private function updateFieldWhoisDetails($companyDomain, $details): void
    {
        $companyDomain->getCompany()->addUpdatedField(
            PluginInstallSubscriber::FIELD_DATA['alias'],
            json_encode($this->transformDetails($details), JSON_FORCE_OBJECT)
        );
        $this->companiesModel->saveEntity($companyDomain->getCompany());
    }

    private function transformDetails($details): array
    {
        return [
            'whois_company_name' => $details['company_name'],
            'whois_is_isp'       => $details['is_isp'],
            'whois_is_dialup'    => $details['is_dialup'],
        ];
    }

    private function newCompanyDomain($domain, Lead $lead, $details = []): FloodlightCompanyDomains
    {
        $this->newCompany($domain, $lead, $details);
        $companies     = $this->leadModel->getCompanies($lead);
        $company       = $this->companiesModel->getEntity($companies[0]['company_id']);
        $companyDomain = new FloodlightCompanyDomains();
        $companyDomain->setDomain($domain);
        $companyDomain->setDateAdded(new \DateTime());
        $companyDomain->setDateModified(new \DateTime());
        $companyDomain->setIsPrimaryDomain(true);
        $companyDomain->setCompany($company);
        $this->saveEntity($companyDomain);

        return $companyDomain;
    }

    private function newCompany($domain, Lead $lead, $details): array
    {
        $companyEntity = $this->companiesModel->getEntity();
        $userIds       = $this->notificationUserHelper->getAdminUsers();
        $user          = $this->userModel->getEntity($userIds[0]);
        $companyEntity->setName($domain);
        $companyEntity->setDateAdded(new \DateTime());
        $companyEntity->setDateModified(new \DateTime());
        $companyEntity->setIsPublished(true);
        $companyEntity->setCreatedBy($user);
        $companyEntity->setModifiedBy($user);
        $companyEntity->setOwner($user);
        if (!empty($details)) {
            $companyEntity->addUpdatedField(PluginInstallSubscriber::FIELD_DATA['alias'], json_encode($this->transformDetails($details)));
        }
        $this->companiesModel->saveEntity($companyEntity);
        $this->leadModel->addToCompany($lead, $companyEntity);
        $lead->setPrimaryCompany($companyEntity);
        $this->leadModel->saveEntity($lead);

        return [
            'company' => $companyEntity,
            'lead'    => $lead,
        ];
    }

    public function updateOrCreateCompanyAndCompanyDomainAndAddOrCountLeadDomain(int $lead, string $domain, $domainDetails = [])
    {
        $leadEntity    = $this->leadModel->getEntity($lead);
        $companyDomain = $this->getRepository()->findOneBy(
            [
                'domain' => $domain,
            ]
        );
        if (
            empty($companyDomain)
            && IpLookupRulesHelper::LEAD_DOMAINS_STATUS_FAILURE !== $domain
            && IpLookupRulesHelper::LEAD_DOMAINS_STATUS_BLACKLISTED !== $domain
        ) {
            $companyDomain = $this->save($domain, $leadEntity, $domainDetails);
        }

        $leadDomain = $this->leadDomainsModel->getRepository()->findOneBy(
            [
                'lead'   => $lead,
                'domain' => $domain,
            ]
        );

        if (empty($leadDomain)) {
            $leadDomain = $this->leadDomainsModel->getEntity();
            $leadDomain->setLead($leadEntity);
            $leadDomain->setDomain($domain);
            $leadDomain->setDateAdded(new \DateTime());
            $leadDomain->setCount(0);
        }

        $leadDomain->setDateModified(new \DateTime());
        $leadDomain->setCount($leadDomain->getCount() + 1);

        $this->em->persist($leadDomain);
        $this->em->flush();

        return $leadDomain;
    }
}
