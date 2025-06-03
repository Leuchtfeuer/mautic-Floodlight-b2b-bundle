<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy;

use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
class Status202Strategy
{

    public function __construct(
        private readonly FloodlightLeadDomainsModel $leadDomainsModel,

    )    {}
    public function prepareData(array $data): array
    {
        $floodlightDTO = new FloodlightDTO($data);

        $status = FloodlightLeadDomains::STATUS_RETRY;
        $newFloodlightDTO = new FloodlightDTO([]);
        $newFloodlightDTO->setIpHash($floodlightDTO->getIpHash());
        $newFloodlightDTO->setDomains([]);
        $floodlightDTO = $newFloodlightDTO;


        return [
            'status'  => $status,
            'domain'  => null,
            'details' => [
                'floodlight'    => $floodlightDTO,
                'statusRequest' => $data['statusRequestCode'],
            ],
        ];

    }

    public function process(array $processedData, $lead)
    {
        $leadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $processedData);
        $this->leadDomainsModel->setStatusToRetryLimitExceededIfTooHighCount($leadDomain);
    }
}