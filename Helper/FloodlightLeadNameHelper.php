<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FloodlightLeadNameHelper extends AbstractExtension
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('floodlight_lead_name', $this->getFloodlightLeadName(...)),
        ];
    }

    public function getFloodlightLeadName(Lead $lead, bool $lastFirst = false): ?string
    {
        if ($lead->getName($lastFirst)) {
            return null;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fld.domain, fld.dateAdded')
            ->from(FloodlightLeadDomains::class, 'fld')
            ->where('fld.contact = :contact')
            ->andWhere('fld.domain IS NOT NULL')
            ->andWhere('fld.domain != :empty')
            ->orderBy('fld.dateAdded', 'ASC')
            ->setMaxResults(1)
            ->setParameter('contact', $lead)
            ->setParameter('empty', '');

        $result = $qb->getQuery()->getOneOrNullResult();

        if (is_array($result) && isset($result['domain'], $result['dateAdded'])) {
            $domain    = $result['domain'];
            $timestamp = $result['dateAdded'] instanceof \DateTime ? $result['dateAdded']->format('Ymd-H:i') : '';

            return 'anonymous '.$timestamp.' '.$domain;
        }

        return null;
    }
}
