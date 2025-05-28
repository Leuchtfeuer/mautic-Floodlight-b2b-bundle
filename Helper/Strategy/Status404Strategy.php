<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;

class Status404Strategy
{
    public function __construct(
        private FloodlightLeadDomainsModel $leadDomainsModel,
    )    {}
    public function prepareData(array $data): array
    {
        $floodlightDTO = new FloodlightDTO($data);
        $status = FloodlightLeadDomains::STATUS_NO_DATA_FOUND;


        return [
            'status'  => $status,
            'domain'  => null,
            'details' => [
                'floodlight'    => $floodlightDTO,
                'statusRequest' => $data['statusRequestCode'],
            ],
        ];

    }

    public function processData(array $processedData, $lead)
    {
        $leadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $processedData);
        //@TODO Implement saving IpHash when 404
        //@TODO add delete old retry entry logic
        //$this->leadDomainsModel->deleteOldRetryEntries($processedData['details']['floodlight'], $lead);
    }
}