<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

class FloodlightCompanyDomainsRepository extends CommonRepository
{
    /**
     * @return array<mixed>
     */
    public function getCompaniesByLeadId(int $leadId, ?string $domain = null): array
    {
        $q = $this->_em->getConnection()->createQueryBuilder();

        $q->select('cl.company_id, cl.date_added as date_associated, cl.is_primary, comp.*, cd.domain, cd.is_primary_domain')
            ->from(MAUTIC_TABLE_PREFIX.'f4b_companies_leads', 'cl')
            ->join('cl', MAUTIC_TABLE_PREFIX.'companies', 'comp', 'comp.id = cl.company_id')
            ->leftJoin('comp', MAUTIC_TABLE_PREFIX.'f4b_company_domains', 'cd', 'cd.company_id = comp.id')
            ->where('cl.lead_id = :leadId')
            ->setParameter('leadId', $leadId);

        if ($domain) {
            $q->andWhere(
                $q->expr()->eq('comp.name', ':domain')
            )->setParameter('domain', $domain);
        }

        return $q->executeQuery()->fetchAllAssociative();
    }

    public function isNew(string $domain): bool
    {
        $qb = $this->createQueryBuilder('cd')
            ->select('cd')
            ->where('cd.domain = :domain')
            ->andWhere('cd.dateAdded > :date')
            ->setParameter('domain', $domain)
            ->setParameter('date', new \DateTime('-30 day'));
        $result = $qb->getQuery()->getResult();
        if (empty($result)) {
            return true;
        }

        return false;
    }
}
