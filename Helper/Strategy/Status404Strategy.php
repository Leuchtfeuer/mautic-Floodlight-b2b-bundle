<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy;

use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;

class Status404Strategy
{
    public function __construct(
        private FloodlightLeadDomainsModel $leadDomainsModel,
    )    {}

    /**
     * @param mixed[] $data
     * @return mixed[]
     */
    public function prepareData(array $data): array
    {
        $floodlightDTO = new FloodlightDTO($data);
        $status = FloodlightLeadDomains::STATUS_NO_DATA_FOUND;

        return [
            'status'  => $status,
            'domain'  => null,
            'lookupType' => FloodlightLeadDomains::LOOKUP_TYPE_API,
            'details' => [
                'floodlight'    => $floodlightDTO,
                'statusRequest' => $data['statusRequestCode'],
            ],
        ];
    }

    /**
     * @param mixed[] $processedData
     */
    public function process(array $processedData, Lead $lead): void
    {
        $leadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $processedData);
        $this->leadDomainsModel->deleteOldRetryEntries($processedData['details']['floodlight'], $lead);
    }
}