<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomainsRepository;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomainsRepository;
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
        protected CompanyModel $companyModel,
        protected LeadModel $leadModel,
    ) {
        parent::__construct($em, $security, $dispatcher, $router, $translator, $userHelper, $logger, $coreParametersHelper);
    }

    public function getRepository(): FloodlightLeadDomainsRepository
    {
        return $this->em->getRepository(FloodlightLeadDomains::class);
    }

    public function getRepositoryFloodlightCompanyDomains(): FloodlightCompanyDomainsRepository
    {
        return $this->em->getRepository(FloodlightCompanyDomains::class);
    }

    /**
     * @param int|null $id
     */
    public function getEntity($id = null): FloodlightLeadDomains
    {
        if (null === $id) {
            return new FloodlightLeadDomains();
        }

        return parent::getEntity($id);
    }

    public function isBlackListed(string $domain): bool
    {
        $blacklist = (new DomainBlacklistHelper())->get();
        if (!empty($blacklist) && in_array($domain, $blacklist, true)) {
            return true;
        }
        foreach ($blacklist as $blacklistedDomain) {
            if (fnmatch('*.'.$blacklistedDomain, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<> $domainDetails
     */
    public function addNewLeadDomainOrCountPlusOneInLeadDomain(Lead $lead, array $dataDomain): FloodlightLeadDomains
    {

        $leadDomain = $this->getRepository()->findOneBy(
            [
                'contact'   => $lead,
                'domain'    => $dataDomain['domain'],
            ]
        );

        if (empty($leadDomain)) {
            $leadDomain = $this->getEntity();
            $leadDomain->setContact($lead);
            $leadDomain->setDomain($dataDomain['domain']);
            $leadDomain->setDateAdded(new \DateTime());
            $leadDomain->setCount(0);
            $leadDomain->setIpHash('local');
            if (
                array_key_exists('floodlight', $dataDomain['details'])
                && $dataDomain['details']['floodlight'] instanceof FloodlightDTO
            ) {
                $leadDomain->setIpHash($dataDomain['details']['floodlight']->getIpHash());
            }
        }
        assert($leadDomain instanceof FloodlightLeadDomains);
        //        $leadDomain->setDateModified(new \DateTime());
        $leadDomain->setCount($leadDomain->getCount() + 1);
        $this->em->persist($leadDomain);
        $this->em->flush();

        return $leadDomain;
    }

    /**
     * @return array<string,array|FloodlightLeadDomains> $details
     */
    public function getLeadDomainToEnrichement(): array
    {
        $qb = $this->getRepository()->createQueryBuilder('l');
        $qb->where('l.ipHash <> :local')
            ->andWhere('l.domain IS NULL')
            ->andWhere('l.dateModified IS NULL OR l.dateModified = :date_modified')
            ->setParameter('local', 'local')
            ->setParameter('date_modified', '1970-01-01 00:00:00')
            ->orderBy('l.dateModified', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function recalculateLeadPrimaryCompany(Lead $lead): void
    {
        $qb = $this->getRepository()->createQueryBuilder('l');
        $qb->where('l.contact = :lead')
            ->andWhere('l.domain IS NOT NULL')
            ->setParameter('lead', $lead);

        $leadDomains = $qb->getQuery()->getResult();

        $maxCount      = 0;
        $maxLeadDomain = null;
        foreach ($leadDomains as $leadDomain) {
            if (0 === $maxCount && null === $maxLeadDomain) {
                $maxLeadDomain = $leadDomain;
                $maxCount      = $leadDomain->getCount();
            }

            if ($leadDomain->getCount() > $maxCount) {
                $maxCount      = $leadDomain->getCount();
                $maxLeadDomain = $leadDomain;
            }
        }

        assert($maxLeadDomain instanceof FloodlightLeadDomains);

        $companyDomain = $this->getRepositoryFloodlightCompanyDomains()->findOneBy(
            [
                'domain'          => $maxLeadDomain->getDomain(),
                'isPrimaryDomain' => true,
            ]
        );

        if (empty($companyDomain)) {
            return;
        }

        assert($companyDomain instanceof FloodlightCompanyDomains);
        $company = $companyDomain->getCompany();
        $lead->setPrimaryCompany($company);
        $this->leadModel->saveEntity($lead);
    }
}
