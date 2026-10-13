<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Mautic\LeadBundle\Entity\Lead;

#[AsEntityListener(event: Events::postLoad, entity: Lead::class)]
class LeadPrimaryIdentifierListener
{
    public function postLoad(Lead $lead): void
    {
        $lead->setPrimaryIdentifierOrder(['name', 'email', 'company', 'social', 'ip']);
    }
}
