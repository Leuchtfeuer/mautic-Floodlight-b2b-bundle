<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FloodlightLeadNameHelper extends AbstractExtension
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('floodlight_lead_name', [$this, 'getFloodlightLeadName']),
        ];
    }

    /**
     * Erstellt einen Floodlight-spezifischen Lead-Namen wenn kein regulärer Name vorhanden ist.
     */
    public function getFloodlightLeadName(Lead $lead, bool $lastFirst = false): ?string
    {
        // Wenn bereits ein Name vorhanden ist, nichts zurückgeben
        if ($lead->getName($lastFirst)) {
            return null;
        }

        // Query für domain und date_added von FloodlightLeadDomains
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

        if ($result) {
            $domain = $result['domain'];
            $timestamp = $result['dateAdded']->format('Ymd-H:i');
            return $domain . ' anonymous ' . $timestamp;
        }

        return null;
    }
} 