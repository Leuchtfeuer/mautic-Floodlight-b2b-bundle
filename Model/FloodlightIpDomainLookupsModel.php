<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model;

use Doctrine\ORM\EntityRepository;
use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\CoreBundle\Model\FormModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightIpDomainLookups;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightIpDomainLookupsRepository;

class FloodlightIpDomainLookupsModel extends FormModel
{
    public function getRepository(): EntityRepository|FloodlightIpDomainLookupsRepository|CommonRepository
    {
        return $this->em->getRepository(FloodlightIpDomainLookups::class);
    }
}
