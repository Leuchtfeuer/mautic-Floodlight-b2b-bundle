<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FloodlightLeadDomainsModel extends FormModel
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
        private LeadModel $leadModel,
        protected CompanyModel $companyModel,
    ) {
        parent::__construct($em, $security, $dispatcher, $router, $translator, $userHelper, $logger, $coreParametersHelper);
    }

    public function getRepository()
    {
        return $this->em->getRepository(FloodlightLeadDomains::class);
    }

    public function getEntity($id = null): ?object
    {
        if (null === $id) {
            return new FloodlightLeadDomains();
        }

        return parent::getEntity($id);
    }

    public function saveOrCount($lead, $domain)
    {
        $leadDomain = $this->getRepository()->findOneBy(
            [
                'lead'   => $lead,
                'domain' => $domain,
            ]
        );

        if (empty($leadDomain)) {
            $leadDomain = $this->getEntity();
            $leadEntity = $this->leadModel->getEntity($lead);
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

    public function isBlackListed($domain): bool
    {
        $blacklist = (new DomainBlacklistHelper())->get();
        if (!empty($blacklist) && in_array($domain, $blacklist, true)) {
            return true;
        }

        return false;
    }
}
