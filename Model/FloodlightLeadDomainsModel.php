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

        $entity = parent::getEntity($id);

        if (null === $entity) {
            return new FloodlightLeadDomains();
        }

        assert($entity instanceof FloodlightLeadDomains);

        return $entity;
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
     * @param array<string,string|mixed> $dataDomain
     */
    public function addNewLeadDomainOrCountPlusOneInLeadDomain(Lead $lead, array $dataDomain): FloodlightLeadDomains
    {
        $leadDomain = $this->getRepository()->findOneBy(
            [
                'contact'   => $lead,
                'domain'    => $dataDomain['domain'],
                //ipHash
                //status
            ]
        );

        if (empty($leadDomain)) {
            $leadDomain = $this->getEntity();
            $leadDomain->setContact($lead);
            if (is_string($dataDomain['domain'])) {
                $leadDomain->setDomain($dataDomain['domain']);
            }
            $leadDomain->setDateAdded(new \DateTime());
            $leadDomain->setCount(0);
            $leadDomain->setIpHash('local');
            if (
                is_array($dataDomain)
                && array_key_exists('details', $dataDomain)
                && is_array($dataDomain['details'])
                && array_key_exists('floodlight', $dataDomain['details'])
                && $dataDomain['details']['floodlight'] instanceof FloodlightDTO
            ) {
                $leadDomain->setIpHash($dataDomain['details']['floodlight']->getIpHash());
            }
            $leadDomain->setStatus($dataDomain['status'] ?? FloodlightLeadDomains::STATUS_UNKNOWN_ERROR);
        }
        assert($leadDomain instanceof FloodlightLeadDomains);
        $leadDomain->setCount($leadDomain->getCount() + 1);
        $leadDomain->setStatus($dataDomain['status'] ?? $leadDomain->getStatus());
        $this->em->persist($leadDomain);
        $this->em->flush();

        if($leadDomain->getStatus() !== FloodlightLeadDomains::STATUS_RETRY) {
            $domainToDelete = $this->getRepository()->findOneBy(
                [
                    'contact'   => $lead,
                    'domain'    => $dataDomain['domain'],
                    //status => FloodlightLeadDomains::STATUS_RETRY
                ]
            );

            if (!empty($domainToDelete)) {
                $this->getRepository()->deleteEntity($domainToDelete);
            }
        }

        else {
            if ($leadDomain->getCount() >= 3) {
                $leadDomain->setStatus(FloodlightLeadDomains::STATUS_RETRY_LIMIT_EXCEEDED);
            }
        }

        //Status von aktuellem request checken
        //Falls 404 oder 200
            //Checken ob Eintrag für gleichen Contact und IP Hash mit Status retry existiert --> löschen
        //Falls 200 und count <= 3
            //Status auf retry limit exceeded setzen

        return $leadDomain;
    }

    /**
     * @return array<FloodlightLeadDomains>
     */
    public function getLeadDomainToEnrichement(): array
    {
        $qb = $this->getRepository()->createQueryBuilder('l');
        $qb->where('l.ipHash <> :local AND l.ipHash IS NOT NULL')
            ->andWhere('l.domain IS NULL')
            ->andWhere('l.dateLastLookup IS NULL OR l.dateLastLookup = :dateLastLookup')
            ->setParameter('local', 'local')
            ->setParameter('dateLastLookup', '1970-01-01 00:00:00')
            ->orderBy('l.dateAdded', 'ASC');

        $result = $qb->getQuery()->getResult();
        if (empty($result) || !is_array($result)) {
            return [];
        }
        $leadDomains = [];
        foreach ($result as $leadDomain) {
            assert($leadDomain instanceof FloodlightLeadDomains);
            $leadDomains[] = $leadDomain;
        }

        return $leadDomains;
    }

    public function recalculateLeadPrimaryCompany(Lead $lead): void
    {
        $qb = $this->getRepository()->createQueryBuilder('l');
        $qb->where('l.contact = :lead')
            ->andWhere('l.domain IS NOT NULL')
            ->setParameter('lead', $lead);

        $leadDomains = $qb->getQuery()->getResult();

        if (empty($leadDomains)) {
            return;
        }

        $maxCount      = 0;
        $maxLeadDomain = 0;
        $isFirst       = true;

        // test it
        if (!is_array($leadDomains)) {
            return;
        }

        foreach ($leadDomains as $leadDomain) {
            assert($leadDomain instanceof FloodlightLeadDomains);
            if ($isFirst) {
                $isFirst       = false;
                $maxLeadDomain = $leadDomain;
                $maxCount      = $leadDomain->getCount();
                continue;
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
        $this->logger->info('Update set primary domain company to '.$companyDomain->getCompany()->getName().' for lead '.$lead->getId());
        $company = $companyDomain->getCompany();
        $lead->setPrimaryCompany($company);
        $this->leadModel->saveEntity($lead);
    }
}
