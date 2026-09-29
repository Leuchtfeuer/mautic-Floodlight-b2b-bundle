<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model;

use Mautic\CoreBundle\Model\FormModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomainsRepository;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomainsRepository;
use Symfony\Contracts\Service\Attribute\Required;

class FloodlightLeadDomainsModel extends FormModel
{
    protected CompanyModel $companyModel;

    protected LeadModel $leadModel;

    private FloodlightLeadDomainsRepository $floodlightLeadDomainsRepository;

    private FloodlightCompanyDomainsRepository $floodlightCompanyDomainsRepository;

    #[Required]
    public function autowireFloodlightLeadDomainsModel(
        CompanyModel $companyModel,
        LeadModel $leadModel,
        FloodlightLeadDomainsRepository $floodlightLeadDomainsRepository,
        FloodlightCompanyDomainsRepository $floodlightCompanyDomainsRepository,
    ): void {
        $this->companyModel                       = $companyModel;
        $this->leadModel                          = $leadModel;
        $this->floodlightLeadDomainsRepository    = $floodlightLeadDomainsRepository;
        $this->floodlightCompanyDomainsRepository = $floodlightCompanyDomainsRepository;
    }

    public function getRepository(): FloodlightLeadDomainsRepository
    {
        return $this->floodlightLeadDomainsRepository;
    }

    public function getRepositoryFloodlightCompanyDomains(): FloodlightCompanyDomainsRepository
    {
        return $this->floodlightCompanyDomainsRepository;
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

    /**
     * @param array{
     *     status: string,
     *     domain: string|null,
     *     lookupType: string,
     *     details: mixed
     * } $dataDomain
     */
    public function addNewLeadDomainOrCountPlusOneInLeadDomain(Lead $lead, array $dataDomain): FloodlightLeadDomains
    {
        $leadDomain = $this->getLeadDomainByContactDomainStatusAndIphash($lead, $dataDomain);

        $this->increaseLeadDomainCount($leadDomain);
        // set status should be removed at later point
        $leadDomain->setStatus($dataDomain['status']);
        $leadDomain->setDateModified(new \DateTime());
        $leadDomain->setLookupType($dataDomain['lookupType']);
        $this->em->persist($leadDomain);
        $this->em->flush();

        return $leadDomain;
    }

    /**
     * @param array{
     *     status: string,
     *     domain: string|null,
     *     lookupType: string,
     *     details: mixed
     * } $dataDomain
     */
    public function getLeadDomainByContactDomainStatusAndIphash(Lead $lead, array $dataDomain): FloodlightLeadDomains
    {
        $ipHash = null;
        if (isset($dataDomain['details'])
            && is_array($dataDomain['details'])
            && isset($dataDomain['details']['floodlight'])
            && $dataDomain['details']['floodlight'] instanceof FloodlightDTO
        ) {
            $ipHash = $dataDomain['details']['floodlight']->getIpHash();
        }

        $floodlightLeadDomain = $this->getRepository()->findOneBy(
            [
                'contact'    => $lead,
                'domain'     => $dataDomain['domain'],
                'status'     => $dataDomain['status'],
                'ipHash'     => $ipHash,
            ]
        );

        if (empty($floodlightLeadDomain)) {
            return $this->floodlightLeadDomainFactory($lead, $dataDomain);
        }

        \assert($floodlightLeadDomain instanceof FloodlightLeadDomains);

        return $floodlightLeadDomain;
    }

    /**
     * @param array{
     *     status: string,
     *     domain: string|null,
     *     lookupType: string,
     *     details: mixed
     * } $dataDomain
     */
    private function floodlightLeadDomainFactory(Lead $lead, array $dataDomain): FloodlightLeadDomains
    {
        $leadDomain = $this->getEntity();
        $leadDomain->setContact($lead);
        if (is_string($dataDomain['domain'])) {
            $leadDomain->setDomain($dataDomain['domain']);
        }
        $leadDomain->setDateAdded(new \DateTime());
        $leadDomain->setCount(0);
        $leadDomain->setIpHash('local');
        if (
            array_key_exists('details', $dataDomain)
            && is_array($dataDomain['details'])
            && array_key_exists('floodlight', $dataDomain['details'])
            && $dataDomain['details']['floodlight'] instanceof FloodlightDTO
        ) {
            $leadDomain->setIpHash($dataDomain['details']['floodlight']->getIpHash());
        }

        return $leadDomain;
    }

    public function increaseLeadDomainCount(FloodlightLeadDomains $floodlightLeadDomain): void
    {
        $floodlightLeadDomain->setCount($floodlightLeadDomain->getCount() + 1);
    }

    public function deleteOldRetryEntries(FloodlightLeadDomains $leadDomain, Lead $lead): void
    {
        $domainToDelete = $this->getRepository()->findOneBy(
            [
                'contact'    => $lead,
                'ipHash'     => $leadDomain->getIpHash(),
                'status'     => FloodlightLeadDomains::STATUS_RETRY,
            ]
        );

        if (!empty($domainToDelete)) {
            $this->getRepository()->deleteEntity($domainToDelete);
        }
    }

    public function setStatusToRetryLimitExceededIfTooHighCount(FloodlightLeadDomains $leadDomain): void
    {
        if ($leadDomain->getCount() >= 3) {
            $leadDomain->setStatus(FloodlightLeadDomains::STATUS_RETRY_LIMIT_EXCEEDED);
        }
        $this->em->persist($leadDomain);
        $this->em->flush();
    }

    /**
     * @return array<FloodlightLeadDomains>
     */
    public function getLeadDomainToEnrichement(): array
    {
        $qb = $this->getRepository()->createQueryBuilder('l');
        $qb->where('l.ipHash <> :local AND l.ipHash IS NOT NULL')
            ->andWhere('l.domain IS NULL')
            ->andWhere('l.status = :retryStatus')
            ->setParameter('local', 'local')
            ->setParameter('retryStatus', 'retry')
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
        // Query die direkt die aggregierten Counts pro Domain zurückgibt, sortiert nach höchstem Count
        $qb = $this->getRepository()->createQueryBuilder('l');
        $qb->select('l.domain, SUM(l.count) as totalCount, MAX(l.dateModified) as latestDate')
            ->where('l.contact = :lead')
            ->andWhere('l.domain IS NOT NULL')
            ->setParameter('lead', $lead)
            ->groupBy('l.domain')
            ->orderBy('totalCount', 'DESC')
            ->addOrderBy('latestDate', 'DESC');

        $result = $qb->getQuery()->getResult();

        if (empty($result) || !is_array($result) || !isset($result[0]) || !is_array($result[0]) || !isset($result[0]['domain'])) {
            return;
        }

        $primaryDomain = $result[0]['domain'];
        $companyDomain = $this->getRepositoryFloodlightCompanyDomains()->findOneBy([
            'domain' => $primaryDomain,
        ]);

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
