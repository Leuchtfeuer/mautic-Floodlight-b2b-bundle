<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

class FloodlightIpDomainLookupsRepository extends CommonRepository
{
    public function isNew(string $ip): bool
    {
        $qb = $this->createQueryBuilder('l')
            ->select('l')
            ->where('l.ip = :ip')
            ->andWhere('l.dateAdded > :date')
            ->setParameter('ip', $ip)
            ->setParameter('date', new \DateTime('-30 day'));
        $result = $qb->getQuery()->getResult();
        if (empty($result)) {
            return true;
        }

        return false;
    }
}
